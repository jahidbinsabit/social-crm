import express from 'express';
import https from 'https';
import {
  makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  downloadMediaMessage,
} from '@whiskeysockets/baileys';
import QRCode from 'qrcode';
import axios from 'axios';
import pino from 'pino';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import { mkdirSync } from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const sessions = new Map();

const app = express();
// Media travels as base64 in the JSON body, so the default 100kb limit is far
// too small.
app.use(express.json({ limit: '80mb' }));

const LARAVEL_URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const INTERNAL_SECRET = process.env.INTERNAL_API_SECRET || 'crm-internal-secret-2024';
const PORT = process.env.PORT || 3001;
const LARAVEL_USE_HTTPS = LARAVEL_URL.startsWith('https:');

// Force IPv4 + keep-alive HTTPS agent to reduce TLS reset / ISP blocking issues.
const customAgent = new https.Agent({
  keepAlive: true,
  keepAliveMsecs: 20000,
  family: 4,
  timeout: 60000,
});

async function notifyLaravel(accountId, data) {
  try {
    let endpoint;
    let payload;

    if (data.event === 'incoming') {
      endpoint = '/api/internal/wa-incoming';
      payload = {
        accountId: String(accountId),
        from: data.from,
        body: data.body,
        timestamp: data.timestamp,
        messageType: data.messageType || 'text',
        media: data.media || null,
        mediaMime: data.mediaMime || null,
        mediaName: data.mediaName || null,
      };
    } else if (data.event === 'qr_ready') {
      endpoint = '/api/internal/wa-qr';
      payload = { accountId: String(accountId) };
    } else {
      endpoint = '/api/internal/wa-event';
      payload = { accountId: String(accountId), event: data.event };
    }

    await axios.post(`${LARAVEL_URL}${endpoint}`, payload, {
      headers: {
        'Content-Type': 'application/json',
        'X-Internal-Secret': INTERNAL_SECRET,
      },
      // Media uploads need a longer window and unbounded body size.
      timeout: payload.media ? 60000 : 8000,
      maxBodyLength: Infinity,
      maxContentLength: Infinity,
      ...(LARAVEL_USE_HTTPS ? { httpsAgent: customAgent } : {}),
    });

  } catch (err) {
    if (err.response) {
      console.error(`Laravel notify failed [${err.response.status}]:`, err.response.data);
    } else {
      console.error('Laravel notify error:', err.message);
    }
  }
}

// Baileys nests each media kind under its own key; this maps that key to the
// message_type the CRM stores.
const MEDIA_KINDS = [
  ['imageMessage', 'image'],
  ['videoMessage', 'video'],
  ['audioMessage', 'audio'],
  ['documentMessage', 'document'],
  ['stickerMessage', 'sticker'],
];

const MAX_MEDIA_BYTES = 64 * 1024 * 1024;

/**
 * Turn a raw Baileys message into the payload Laravel expects, downloading
 * any attachment to base64. Returns null when there is nothing to forward.
 */
async function extractIncoming(msg, socket) {
  // Unwrap nested message containers. Keep reference to original msg for download.
  const content =
    msg.message?.ephemeralMessage?.message ||
    msg.message?.viewOnceMessage?.message ||
    msg.message?.viewOnceMessageV2?.message ||
    msg.message?.documentWithCaptionMessage?.message ||
    msg.message;

  if (!content) return null;

  const text = content.conversation || content.extendedTextMessage?.text || '';

  for (const [key, type] of MEDIA_KINDS) {
    const node = content[key];
    if (!node) continue;

    // Skip anything too large to round-trip through base64 JSON.
    if (node.fileLength && Number(node.fileLength) > MAX_MEDIA_BYTES) {
      return {
        body: `[${type} too large to download]`,
        messageType: 'text',
      };
    }

    try {
      // Pass the FULL original message, not the unwrapped content.
      // downloadMediaMessage needs the outer message for decryption key resolution.
      // For view-once/ephemeral, Baileys handles unwrapping internally.
      const buffer = await downloadMediaMessage(
        msg,
        'buffer',
        {},
        { logger: pino({ level: 'silent' }), reuploadRequest: socket.updateMediaMessage }
      );

      return {
        body: node.caption || '',
        messageType: type,
        media: buffer.toString('base64'),
        mediaMime: node.mimetype || null,
        mediaName: node.fileName || null,
      };
    } catch (err) {
      console.error(`Media download failed (${type}):`, {
        message: err.message,
        msgId: msg.key?.id,
        jid: msg.key?.remoteJid,
        type,
      });
      return { body: node.caption || `[${type} failed to download]`, messageType: 'text' };
    }
  }

  if (!text) return null;

  return { body: text, messageType: 'text' };
}

async function initSession(accountId, phoneNumber, mode = 'qr', retryCount = 0) {
  const authPath = join(__dirname, 'sessions', accountId);
  mkdirSync(authPath, { recursive: true });
  const { state, saveCreds } = await useMultiFileAuthState(authPath);

  // Preserve QR and pairing code across reconnects so the admin UI can
  // still fetch them after a transient disconnect.
  const existing = sessions.get(accountId);
  if (existing?.socket) {
    try {
      existing.socket.ev.removeAllListeners();
      existing.socket.end(undefined);
    } catch (_) {
      // ignore
    }
  }

  const preservedQr = existing?.qr ?? null;
  const preservedPairingCode = existing?.pairingCode ?? null;
  const wasPairingRequested = existing?.pairingCodeRequested ?? false;

  // Track retry count to prevent infinite reconnect loops
  const maxRetries = 5;
  const isRetry = retryCount > 0;

  if (isRetry) {
    console.log(`[init] Session ${accountId} retry ${retryCount}/${maxRetries}`);
    if (retryCount >= maxRetries) {
      console.error(`[init] Session ${accountId} exceeded max retries — marking disconnected`);
      sessions.set(accountId, {
        socket: null,
        qr: preservedQr,
        status: 'disconnected',
        phoneNumber,
        saveCreds,
        mode,
        pairingCode: preservedPairingCode,
        pairingCodeRequested: wasPairingRequested,
        lastActivity: Date.now(),
        retryCount,
      });
      await notifyLaravel(accountId, { event: 'error', message: 'Connection failed after multiple retries. Please re-scan QR.' });
      return;
    }
  }

  // FIXED: No special option needed for pairing in Baileys 6.7.
  // requestPairingCode() works on any normal socket — called after 'open'.
  const socket = makeWASocket({
    logger: pino({ level: 'silent' }),
    printQRInTerminal: false,
    auth: state,
    fetchOpts: {
      agent: customAgent,
    },
    // If your server can't reach WhatsApp directly, uncomment and set a proxy:
    // proxy: { address: 'http://your-proxy:port', username: '', password: '' },
  });

  sessions.set(accountId, {
    socket,
    qr: preservedQr,
    status: 'connecting',
    phoneNumber,
    saveCreds,
    mode,
    pairingCode: preservedPairingCode,
    pairingCodeRequested: wasPairingRequested,
    lastActivity: Date.now(),
    retryCount,
  });

  // ── connection.update ─────────────────────────────────────────────────────
  socket.ev.on('connection.update', async (update) => {
    const { connection, qr, lastDisconnect } = update;

    // QR mode: capture QR and notify Laravel
    if (qr && mode === 'qr') {
      const session = sessions.get(accountId);
      if (session) session.qr = qr;
      await notifyLaravel(accountId, { event: 'qr_ready' });
    }

    if (connection === 'open') {
      const session = sessions.get(accountId);
      if (session) {
        session.status = 'active';
        session.qr = null;
        session.lastActivity = Date.now();
        session.retryCount = 0; // Reset on successful connection
      }

      if (mode === 'pairing') {
        const session = sessions.get(accountId);
        if (session && !session.pairingCodeRequested) {
          // First open after socket init — request the pairing code
          session.pairingCodeRequested = true;
          try {
            const cleanPhone = phoneNumber.replace(/\D/g, '');
            const code = await socket.requestPairingCode(cleanPhone);
            // Store the code so it survives reconnects
            session.pairingCode = code;
            await notifyLaravel(accountId, { event: 'pairing_code', code });
          } catch (err) {
            console.error('[pairing] requestPairingCode failed:', err.message);
            if (session) session.status = 'disconnected';
            await notifyLaravel(accountId, { event: 'error', message: err.message });
          }
        } else if (session && session.pairingCodeRequested && session.status === 'active') {
          // Second open — user accepted the code — notify Laravel it's connected
          await notifyLaravel(accountId, { event: 'connected' });
        }
      } else {
        // QR mode connected
        await notifyLaravel(accountId, { event: 'connected' });
      }
    }

    if (connection === 'close') {
      const session = sessions.get(accountId);
      const statusCode = lastDisconnect?.error?.output?.statusCode;
      const isLoggedOut = statusCode === DisconnectReason.loggedOut;

      if (isLoggedOut) {
        sessions.delete(accountId);
        await notifyLaravel(accountId, { event: 'disconnected' });
      } else if (statusCode !== undefined) {
        // Reconnect on temporary disconnects — increment retry count
        const currentRetry = (session?.retryCount ?? 0) + 1;
        if (session) {
          session.status = 'reconnecting';
          session.pairingCodeRequested = false;
          session.retryCount = currentRetry;
        }
        const delay = Math.min(3000 * currentRetry, 30000); // Back off: 3s, 6s, 9s... max 30s
        console.log(`[close] Session ${accountId} disconnected (code ${statusCode}), retry ${currentRetry} in ${delay}ms`);
        setTimeout(() => initSession(accountId, phoneNumber, mode, currentRetry), delay);
      }
    }
  });

  // ── messages.upsert — FIXED: now registered for BOTH qr and pairing modes ─
  socket.ev.on('messages.upsert', async (messageUpdate) => {
    if (!messageUpdate?.messages) return;

    // Update activity timestamp to prevent health check from killing active sessions
    const actSession = sessions.get(accountId);
    if (actSession) actSession.lastActivity = Date.now();

    for (const msg of messageUpdate.messages) {
      if (msg.key?.fromMe) continue;
      const remoteJid = msg.key?.remoteJid;
      if (!remoteJid) continue;

      // Filter out status broadcasts, group chats, and newsletter channels
      if (
        remoteJid === 'status@broadcast' ||
        remoteJid.endsWith('@g.us') ||
        remoteJid.endsWith('@newsletter') ||
        remoteJid.endsWith('@broadcast')
      ) {
        continue;
      }

      const extracted = await extractIncoming(msg, socket);
      if (!extracted) continue;

      const pushName = msg.pushName || null;

      await notifyLaravel(accountId, {
        event: 'incoming',
        from: remoteJid,
        displayName: pushName,
        timestamp: msg.messageTimestamp
          ? new Date(msg.messageTimestamp * 1000).toISOString()
          : new Date().toISOString(),
        ...extracted,
      });
    }
  });

  // ── creds.update — FIXED: now registered for BOTH modes ──────────────────
  socket.ev.on('creds.update', async () => {
    const session = sessions.get(accountId);
    if (session?.saveCreds) {
      await session.saveCreds();
    }
  });
}

// POST /session/start  (mode: 'qr' | 'pairing')
app.post('/session/start', (req, res) => {
  const { accountId, phoneNumber, mode = 'qr' } = req.body;

  if (!accountId || !phoneNumber) {
    return res.status(400).json({ error: 'accountId and phoneNumber are required' });
  }

  const existing = sessions.get(accountId);
  if (existing && existing.status === 'active') {
    return res.json({ status: 'already_connected' });
  }

  // Remove old session if it exists but isn't active.
  if (existing) {
    sessions.delete(accountId);
  }

  initSession(accountId, phoneNumber, mode);
  return res.json({ status: 'initializing', mode });
});

// GET /session/status/:accountId
app.get('/session/status/:accountId', (req, res) => {
  const { accountId } = req.params;
  const session = sessions.get(accountId);
  res.json({
    accountId,
    status: session?.status || 'not_found',
    mode: session?.mode || 'qr',
    pairingCode: session?.pairingCode || null,
    code: session?.pairingCode || null,
    pairingCodeRequested: session?.pairingCodeRequested || false,
  });
});

// GET /session/qr/:accountId
app.get('/session/qr/:accountId', async (req, res) => {
  const { accountId } = req.params;
  const session = sessions.get(accountId);

  if (!session) {
    return res.json({ status: 'not_found' });
  }
  if (!session.qr) {
    // QR was already consumed — the session is connected or connecting.
    return res.json({ status: session.status || 'connected' });
  }

  try {
    const qrPng = await QRCode.toBuffer(session.qr, { type: 'png' });
    res.set('Content-Type', 'image/png');
    res.send(qrPng);
  } catch (err) {
    console.error('QR generation failed:', err);
    res.status(500).json({ error: 'Failed to generate QR' });
  }
});

// POST /session/disconnect
app.post('/session/disconnect', (req, res) => {
  const { accountId } = req.body;

  if (!accountId) {
    return res.status(400).json({ error: 'accountId is required' });
  }

  const session = sessions.get(accountId);
  if (session?.socket) {
    try {
      session.socket.logout();
    } catch (err) {
      console.error('Logout error:', err.message);
    }
  }

  sessions.delete(accountId);
  return res.json({ status: 'disconnected' });
});

// POST /message/send
// Body: { accountId, jid, text?, media?(base64), mediaMime?, mediaName?, messageType? }
app.post('/message/send', async (req, res) => {
  const { accountId, jid, text, media, mediaMime, mediaName, messageType } = req.body;

  if (!accountId || !jid) {
    return res.status(400).json({ error: 'accountId and jid are required' });
  }

  if (!text && !media) {
    return res.status(400).json({ error: 'either text or media is required' });
  }

  const session = sessions.get(accountId);
  if (!session || session.status === 'disconnected') {
    return res.status(400).json({ error: 'Session disconnected. Please re-scan QR code.' });
  }
  if (!session || session.status !== 'active') {
    return res.status(400).json({ error: `Session not active (current: ${session?.status || 'not_found'}). Try again in a moment.` });
  }

  try {
    const cleanJid = jid.includes('@') ? jid : `${jid}@s.whatsapp.net`;
    const payload = media
      ? buildMediaPayload(messageType, Buffer.from(media, 'base64'), mediaMime, mediaName, text)
      : { text };

    // Add a timeout wrapper so we don't hang forever on slow connections.
    const sendPromise = session.socket.sendMessage(cleanJid, payload);
    const timeoutPromise = new Promise((_, reject) =>
      setTimeout(() => reject(new Error('Send timeout after 45s')), 45000)
    );
    await Promise.race([sendPromise, timeoutPromise]);
    return res.json({ status: 'sent' });
  } catch (err) {
    console.error('Send message failed:', err.message);
    return res.status(500).json({ error: err.message || 'Failed to send message' });
  }
});

/** Shape a Baileys outbound payload for the requested media kind. */
function buildMediaPayload(messageType, buffer, mime, fileName, caption) {
  const type = messageType || inferType(mime);
  const text = caption || undefined;

  switch (type) {
    case 'image':
      return { image: buffer, caption: text, mimetype: mime || 'image/jpeg' };
    case 'video':
      return { video: buffer, caption: text, mimetype: mime || 'video/mp4' };
    case 'audio':
      // ptt:false sends it as a music/audio file rather than a voice note.
      return { audio: buffer, mimetype: mime || 'audio/mpeg', ptt: false };
    case 'sticker':
      return { sticker: buffer };
    default:
      return {
        document: buffer,
        mimetype: mime || 'application/octet-stream',
        fileName: fileName || 'file',
        caption: text,
      };
  }
}

function inferType(mime) {
  const m = (mime || '').toLowerCase();
  if (m.startsWith('image/webp')) return 'sticker';
  if (m.startsWith('image/')) return 'image';
  if (m.startsWith('video/')) return 'video';
  if (m.startsWith('audio/')) return 'audio';
  return 'document';
}

// GET /sessions/list
app.get('/sessions/list', (req, res) => {
  const list = Array.from(sessions.entries()).map(([accountId, session]) => ({
    accountId,
    status: session.status,
    phoneNumber: session.phoneNumber,
  }));
  res.json(list);
});

app.get('/', (req, res) => {
  res.json({
    status: 'ok',
    engine: 'whatsapp',
    sessions: sessions.size,
    secret_configured: !!process.env.INTERNAL_API_SECRET,
  });
});

// Auto-restore active sessions on startup so a restart doesn't require
// re-submitting every account through the admin UI.
async function restoreActiveSessions() {
  try {
    const resp = await axios.get(`${LARAVEL_URL}/api/internal/accounts/active`, {
      headers: { 'X-Internal-Secret': INTERNAL_SECRET },
      timeout: 8000,
    });

    const accounts = resp.data;
    if (!Array.isArray(accounts) || accounts.length === 0) {
      console.log('[restore] No active accounts to restore');
      return;
    }

    for (const acct of accounts) {
      if (acct.platform !== 'whatsapp') continue;
      if (sessions.has(String(acct.id))) continue;
      console.log(`[restore] Reconnecting account ${acct.id} (${acct.phone_number})`);
      initSession(String(acct.id), acct.phone_number, 'qr');
    }
  } catch (err) {
    console.error('[restore] Failed to fetch active accounts:', err.message);
  }
}

// Health check: if a session hasn't had activity in 10 minutes, reconnect it.
// This handles the case where Baileys appears connected but is stuck.
// Only kills sessions that are 'active' — leaves reconnecting ones alone to finish their retry cycle.
setInterval(() => {
  const now = Date.now();
  for (const [accountId, session] of sessions.entries()) {
    // Only force-reconnect sessions that are truly 'active' and stale.
    // Skip 'reconnecting' ones — they're already in their retry cycle.
    const isStale = now - (session.lastActivity || 0) > 10 * 60 * 1000;
    if (session.status === 'active' && isStale) {
      console.log(`[health] Session ${accountId} stale (${Math.round((now - session.lastActivity) / 1000)}s), forcing reconnect`);
      try { session.socket?.end(undefined); } catch (_) { /* ignore */ }
      sessions.delete(accountId);
      initSession(accountId, session.phoneNumber, session.mode);
    }
  }
}, 2 * 60 * 1000); // Check every 2 minutes

app.listen(PORT, () => {
  console.log(`WhatsApp engine listening on port ${PORT}`);
  // Defer so the first QR/event handlers attach immediately.
  setTimeout(restoreActiveSessions, 2000);
});
