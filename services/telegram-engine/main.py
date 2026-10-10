#!/usr/bin/env python3
"""Telegram Telethon Engine for Social CRM."""

import asyncio
import base64
import io
import mimetypes
import os
import socket
from pathlib import Path
from dotenv import load_dotenv

from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException
from telethon import TelegramClient
from telethon.errors import SessionPasswordNeededError, PhoneCodeInvalidError, FloodWaitError, RPCError
from telethon.events import NewMessage
from telethon.network import Connection
from telethon.network.connection.tcpfull import ConnectionTcpFull
from telethon.tl.functions.contacts import ImportContactsRequest
from telethon.tl.types import InputPhoneContact
import httpx
import uvicorn

load_dotenv()

app = FastAPI(title="Telegram Engine")

LARAVEL_URL = os.getenv("LARAVEL_URL", "http://127.0.0.1:8001")
API_ID = int(os.getenv("API_ID", "0"))
API_HASH = os.getenv("API_HASH", "")
INTERNAL_SECRET = os.getenv("INTERNAL_API_SECRET", "crm-internal-secret-2024")
PORT = int(os.getenv("PORT", 3002))

if API_ID == 0 or not API_HASH or API_HASH == "your_telegram_api_hash":
    raise SystemExit(
        "ERROR: API_ID and API_HASH must be set in .env. "
        "Get them from https://my.telegram.org"
    )

sessions: dict[str, dict] = {}


# Custom Telethon connection that forces IPv4 to avoid IPv6/TLS issues
class IPv4Connection(ConnectionTcpFull):
    async def _connect(self, timeout=None, ssl=None):
        try:
            # Force IPv4 by resolving to A records only
            loop = asyncio.get_event_loop()
            infos = await loop.getaddrinfo(
                self._ip, self._port, family=socket.AF_INET
            )
            if not infos:
                raise OSError("No IPv4 addresses found")

            sockaddr = infos[0][4]
            self._ip = sockaddr[0]
            self._port = int(sockaddr[1])
        except Exception:
            # Fallback to original connection if IPv4 resolution fails
            pass

        # Call parent connect
        return await super()._connect(timeout=timeout, ssl=ssl)


async def restore_active_sessions():
    """Fetch active telegram accounts from Laravel and reconnect their sessions.

    Raises on connection or fetch failures so callers can retry. Returns the
    number of sessions successfully restored.
    """
    async with httpx.AsyncClient() as http:
        resp = await http.get(
            f"{LARAVEL_URL}/api/internal/accounts/active",
            headers={"X-Internal-Secret": INTERNAL_SECRET},
            timeout=8,
        )
        resp.raise_for_status()
        accounts = resp.json()
        if not accounts:
            print("[restore] No active accounts to restore")
            return 0

        restored = 0
        for acct in accounts:
            if acct.get("platform") != "telegram":
                continue
            account_id = str(acct["id"])
            if account_id in sessions:
                continue

            session_path = Path(__file__).parent / "sessions" / f"{account_id}.session"
            if not session_path.exists():
                print(f"[restore] No session file for account {account_id}")
                continue

            try:
                client = TelegramClient(
                    str(session_path),
                    API_ID,
                    API_HASH,
                    connection=IPv4Connection,
                )
                await client.connect()
                if not await client.is_user_authorized():
                    await client.disconnect()
                    print(f"[restore] Session not authorized for account {account_id}")
                    continue

                me = await client.get_me()
                phone = "+" + str(me.phone) if me.phone else ""

                sessions[account_id] = {
                    "client": client,
                    "status": "active",
                    "phone": phone,
                }
                _register_message_handler(account_id, client)
                print(f"[restore] Reconnected telegram account {account_id} ({phone})")
                restored += 1
            except Exception as exc:
                print(f"[restore] Failed to restore account {account_id}: {exc}")

        if restored:
            print(f"[restore] Restored {restored} telegram session(s)")
        return restored


async def _restore_with_retry(max_attempts: int = 5, delay: float = 5.0):
    """Run restore_active_sessions with retries if Laravel or Telegram is not ready."""
    for attempt in range(1, max_attempts + 1):
        try:
            await restore_active_sessions()
            return
        except Exception as exc:
            msg = str(exc)
            # Keep retrying on connection errors; bail out on auth/session errors.
            if any(keyword in msg.lower() for keyword in ["connection refused", "unreachable", "timed out", "resolve", "connection error"]):
                if attempt < max_attempts:
                    print(f"[restore] Attempt {attempt} failed ({msg}), retrying in {delay}s...")
                    await asyncio.sleep(delay)
                else:
                    print(f"[restore] All {max_attempts} attempts failed, giving up")
            else:
                # Permanent failure — no point retrying.
                print(f"[restore] Permanent failure: {msg}")
                return


@asynccontextmanager
async def lifespan(_app: FastAPI):
    # Startup: reconnect active Telegram sessions after a short delay so
    # Laravel has time to become reachable if both restart together.
    asyncio.get_event_loop().call_later(2, lambda: asyncio.create_task(_restore_with_retry()))
    yield


app = FastAPI(title="Telegram Engine", lifespan=lifespan)


async def notify_laravel(account_id: str, data: dict):
    async with httpx.AsyncClient(
        timeout=60.0,
        limits=httpx.Limits(max_connections=20, max_keepalive_connections=5),
    ) as client:
        try:
            await client.post(
                f"{LARAVEL_URL}/api/internal/tg-event",
                json={"accountId": account_id, **data},
                headers={"X-Internal-Secret": INTERNAL_SECRET},
            )
        except Exception as exc:
            print(f"Failed to notify Laravel: {exc}")


@app.post("/session/request-otp")
async def request_otp(payload: dict):
    account_id = payload.get("accountId")
    phone_number = payload.get("phoneNumber")

    if not account_id or not phone_number:
        return {"status": "error", "message": "accountId and phoneNumber are required"}

    phone_number = phone_number.strip()
    if not phone_number.startswith("+"):
        phone_number = "+" + phone_number

    session_path = Path(__file__).parent / "sessions" / f"{account_id}.session"
    session_path.parent.mkdir(parents=True, exist_ok=True)

    try:
        client = TelegramClient(str(session_path), API_ID, API_HASH)
        await client.connect()

        result = await client.send_code_request(phone_number)

        sessions[account_id] = {
            "client": client,
            "status": "awaiting_otp",
            "phone_code_hash": result.phone_code_hash,
            "phone": phone_number,
        }

        return {"status": "otp_sent"}

    except Exception as exc:
        print(f"OTP request failed: {exc}")
        return {"status": "error", "message": str(exc)}


@app.post("/session/verify-otp")
async def verify_otp(payload: dict):
    account_id = payload.get("accountId")
    code = payload.get("code")
    phone2fa = payload.get("phone2fa")

    if not account_id or not code:
        return {"status": "error", "message": "accountId and code are required"}

    session_data = sessions.get(account_id)
    if not session_data:
        return {"status": "error", "message": "Session not found"}

    client = session_data["client"]
    phone = session_data["phone"]
    phone_code_hash = session_data["phone_code_hash"]

    try:
        await client.sign_in(phone=phone, code=code, phone_code_hash=phone_code_hash)
    except SessionPasswordNeededError:
        if not phone2fa:
            return {"status": "error", "message": "2FA required", "need_2fa": True}
        await client.sign_in(password=phone2fa)
    except PhoneCodeInvalidError:
        return {"status": "error", "message": "Invalid OTP"}
    except Exception as exc:
        return {"status": "error", "message": str(exc)}

    session_data["status"] = "active"

    _register_message_handler(account_id, client)

    import asyncio
    asyncio.create_task(notify_laravel(account_id, {"event": "connected"}))

    return {"status": "authenticated"}


MAX_MEDIA_BYTES = 64 * 1024 * 1024


def _classify(mime: str, document=None) -> str:
    """Map a Telegram MIME type to the CRM's message_type enum."""
    mime = (mime or "").lower()

    # Telegram marks stickers/animations with document attributes.
    if document is not None:
        for attr in getattr(document, "attributes", []):
            name = type(attr).__name__
            if name == "DocumentAttributeSticker":
                return "sticker"
            if name == "DocumentAttributeAudio":
                return "audio"
            if name == "DocumentAttributeVideo":
                return "video"

    if mime.startswith("image/webp"):
        return "sticker"
    if mime.startswith("image/"):
        return "image"
    if mime.startswith("video/"):
        return "video"
    if mime.startswith("audio/"):
        return "audio"
    return "document"


def _filename_for(document, mime: str) -> str | None:
    """Prefer the sender's original filename, else synthesise one."""
    if document is not None:
        for attr in getattr(document, "attributes", []):
            if type(attr).__name__ == "DocumentAttributeFilename":
                return attr.file_name

    ext = mimetypes.guess_extension(mime or "") or ".bin"
    return f"file{ext}"


async def _extract_media(event) -> dict:
    """Download any attachment on the event and return it as base64."""
    if not event.message or not event.message.media:
        return {"messageType": "text"}

    document = getattr(event.message, "document", None)
    photo = getattr(event.message, "photo", None)

    if document is not None:
        size = getattr(document, "size", 0) or 0
        if size > MAX_MEDIA_BYTES:
            return {"messageType": "text", "oversize": True}
        mime = getattr(document, "mime_type", "") or ""
    elif photo is not None:
        mime = "image/jpeg"
    else:
        # Contacts, polls, geo, etc. — nothing downloadable.
        return {"messageType": "text"}

    try:
        buf = io.BytesIO()
        await event.message.download_media(file=buf)
        data = buf.getvalue()

        if not data or len(data) > MAX_MEDIA_BYTES:
            return {"messageType": "text", "oversize": True}

        return {
            "messageType": _classify(mime, document),
            "media": base64.b64encode(data).decode("ascii"),
            "mediaMime": mime or "image/jpeg",
            "mediaName": _filename_for(document, mime) if document else "photo.jpg",
        }
    except Exception as exc:
        print(f"Telegram media download failed: {exc}")
        return {"messageType": "text", "failed": True}


def _register_message_handler(account_id: str, client: TelegramClient):
    session_data = sessions.get(account_id)
    if session_data and session_data.get("_handler_registered"):
        return
    if session_data:
        session_data["_handler_registered"] = True

    @client.on(NewMessage(incoming=True))
    async def handler(event):
        try:
            # Only process 1-on-1 direct private chats (ignore public channels, groups, broadcasts)
            if not event.is_private:
                return

            try:
                sender = await event.get_sender()
            except Exception:
                sender = None

            chat = None
            try:
                chat = await event.get_chat()
            except Exception:
                pass

            # Safely extract username, phone, sender_id, display_name
            username = getattr(sender, "username", None) if sender else None
            phone = getattr(sender, "phone", None) if sender else None
            first = (getattr(sender, "first_name", "") or getattr(chat, "first_name", "") or "").strip()
            last = (getattr(sender, "last_name", "") or getattr(chat, "last_name", "") or "").strip()
            full_name = f"{first} {last}".strip()

            sender_id = None
            if sender and hasattr(sender, "id"):
                sender_id = str(sender.id)
            elif chat and hasattr(chat, "id"):
                sender_id = str(chat.id)

            phone_formatted = ("+" + str(phone).lstrip("+")) if phone else None
            username_formatted = ("@" + username.lstrip("@")) if username else None

            # Filter out generic/placeholder names or names matching contact phone
            is_generic_name = (
                not full_name or
                full_name.lower() in ["contact", "crm contact", "client", "user", "none", "unknown", "null"] or
                full_name == phone_formatted or
                full_name == (phone or "") or
                full_name.lstrip("+").isdigit()
            )

            # Determine best display_name:
            # 1. Real Telegram Profile Name (if user set a real name)
            # 2. Telegram @username
            # 3. Phone number
            # 4. Telegram sender ID
            if not is_generic_name:
                display_name = full_name
            elif username_formatted:
                display_name = username_formatted
            elif phone_formatted:
                display_name = phone_formatted
            elif sender_id:
                display_name = sender_id
            else:
                display_name = getattr(chat, "title", None) or "User"

            if username:
                from_identifier = username
            elif phone_formatted:
                from_identifier = phone_formatted
            elif sender_id:
                from_identifier = sender_id
            else:
                from_identifier = "unknown"

            text = event.raw_text or ""
            extracted = await _extract_media(event)

            if extracted.pop("oversize", False):
                text = text or "[media too large to download]"
            if extracted.pop("failed", False):
                text = text or "[media failed to download]"

            if not text and "media" not in extracted:
                return

            payload = {
                "accountId": str(account_id),
                "from": from_identifier,
                "senderId": sender_id,
                "phone": phone_formatted,
                "displayName": display_name,
                "body": text,
                "timestamp": str(event.date),
                **extracted,
            }

            has_media = "media" in extracted

            async with httpx.AsyncClient() as http:
                try:
                    await http.post(
                        f"{LARAVEL_URL}/api/internal/tg-incoming",
                        json=payload,
                        headers={"X-Internal-Secret": INTERNAL_SECRET},
                        timeout=60 if has_media else 8,
                    )
                except Exception as exc:
                    print(f"Failed to forward incoming message to Laravel: {exc}")

        except Exception as handler_exc:
            print(f"Error in telegram message handler for account {account_id}: {handler_exc}")


async def _resolve_target(client: TelegramClient, target: str):
    """Resolve target into a sendable entity in Telethon."""
    target = target.strip()

    if target.startswith("@"):
        return target

    clean_digits = target.lstrip("+")
    is_phone_like = clean_digits.isdigit() and (target.startswith("+") or len(clean_digits) >= 10)

    if is_phone_like:
        phone_with_plus = "+" + clean_digits if not target.startswith("+") else target
        try:
            entity = await client.get_input_entity(phone_with_plus)
            return entity
        except Exception:
            pass

        try:
            contact = InputPhoneContact(client_id=0, phone=phone_with_plus, first_name=phone_with_plus, last_name="")
            result = await client(ImportContactsRequest([contact]))
            if result.users:
                return result.users[0]
        except Exception as e:
            print(f"[send] Import contact for {target} failed: {e}")

        raise ValueError(f"Telegram user with phone {phone_with_plus} could not be found or resolved.")

    if clean_digits.isdigit():
        try:
            return int(target)
        except ValueError:
            pass

    return target


@app.post("/session/disconnect")
async def disconnect_session(payload: dict):
    account_id = payload.get("accountId")

    if not account_id:
        return {"status": "error", "message": "accountId is required"}

    session_data = sessions.get(account_id)
    if session_data and session_data.get("client"):
        try:
            await session_data["client"].disconnect()
        except Exception as exc:
            print(f"Disconnect error: {exc}")

    sessions.pop(account_id, None)
    return {"status": "disconnected"}


@app.get("/session/status/{account_id}")
async def session_status(account_id: str):
    if account_id not in sessions:
        return {"accountId": account_id, "status": "not_found"}
    return {"accountId": account_id, "status": sessions[account_id]["status"]}


@app.post("/message/send")
async def send_message(payload: dict):
    account_id = payload.get("accountId")
    username = payload.get("username")
    text = payload.get("text")
    media_b64 = payload.get("media")
    media_mime = payload.get("mediaMime") or ""
    media_name = payload.get("mediaName") or "file"
    message_type = payload.get("messageType") or ""

    if not account_id or not username:
        return {"status": "error", "message": "accountId and username are required"}

    if not text and not media_b64:
        return {"status": "error", "message": "either text or media is required"}

    session_data = sessions.get(account_id)
    if not session_data or session_data.get("status") != "active":
        return {"status": "error", "message": "Session not active"}

    client = session_data["client"]

    # Health check: verify client is actually connected
    if not client.is_connected() or not await client.is_user_authorized():
        print(f"[send] Client {account_id} disconnected, attempting reconnect")
        try:
            await client.connect()
            if not await client.is_user_authorized():
                session_data["status"] = "disconnected"
                return {"status": "error", "message": "Session not authorized"}
        except Exception as exc:
            return {"status": "error", "message": f"Reconnect failed: {exc}"}

    try:
        resolved = await _resolve_target(client, username)
        if not media_b64:
            await client.send_message(resolved, text)
            return {"status": "sent"}

        data = base64.b64decode(media_b64)
        buf = io.BytesIO(data)
        # Telethon infers how to send from the filename's extension.
        buf.name = media_name

        kind = message_type or _classify(media_mime)

        await client.send_file(
            resolved,
            buf,
            caption=text or None,
            # force_document keeps files intact; images/videos send natively so
            # they preview in the Telegram client.
            force_document=(kind == "document"),
            voice_note=False,
        )
        return {"status": "sent"}
    except FloodWaitError as exc:
        print(f"Telegram flood wait required for {username}: wait {exc.seconds}s")
        raise HTTPException(status_code=429, detail=f"Telegram flood wait required: wait {exc.seconds} seconds.")
    except Exception as exc:
        print(f"Telegram send failed to {username}: {exc}")
        # Return 422 so Laravel's ->throw() catches it as an HTTP error
        raise HTTPException(status_code=422, detail=str(exc))


@app.get("/sessions/list")
async def list_sessions():
    return [
        {
            "accountId": account_id,
            "status": data["status"],
            "phone": data.get("phone"),
        }
        for account_id, data in sessions.items()
    ]


@app.get("/")
def health():
    return {"status": "ok", "engine": "telegram"}


@app.post("/session/restore")
async def restore_session(payload: dict):
    """Reconnect an existing Telegram session from disk after a restart."""
    account_id = payload.get("accountId")
    if not account_id:
        return {"status": "error", "message": "accountId is required"}

    if account_id in sessions and sessions[account_id].get("status") == "active":
        return {"status": "already_connected"}

    session_path = Path(__file__).parent / "sessions" / f"{account_id}.session"
    if not session_path.exists():
        return {"status": "not_found", "message": "No session file on disk"}

    try:
        client = TelegramClient(str(session_path), API_ID, API_HASH, connection=IPv4Connection)
        await client.connect()

        if not await client.is_user_authorized():
            await client.disconnect()
            return {"status": "not_authorized", "message": "Session file invalid or revoked"}

        me = await client.get_me()
        phone = "+" + str(me.phone) if me.phone else ""

        sessions[account_id] = {
            "client": client,
            "status": "active",
            "phone": phone,
        }
        _register_message_handler(account_id, client)
        await notify_laravel(account_id, {"event": "connected"})
        return {"status": "restored", "phone": phone}
    except Exception as exc:
        print(f"Restore session {account_id} failed: {exc}")
        return {"status": "error", "message": str(exc)}


if __name__ == "__main__":
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=PORT,
        reload=False,
        loop="asyncio",
    )
