<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Channel — Social CRM</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: #0a0a0f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #fff;
            padding: 24px;
            overflow: hidden;
            position: relative;
        }

        /* Subtle ambient glow */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 25% 40%, rgba(42,171,238,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 75% 60%, rgba(37,211,102,0.06) 0%, transparent 60%);
            pointer-events: none;
        }

        .wrapper {
            width: 100%;
            max-width: 560px;
            position: relative;
            z-index: 1;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header .brand {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            margin-bottom: 16px;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 300;
            color: rgba(255,255,255,0.9);
            line-height: 1.3;
        }
        .header h1 strong {
            font-weight: 700;
            color: #fff;
        }
        .header p {
            font-size: 14px;
            color: rgba(255,255,255,0.35);
            margin-top: 8px;
        }

        .cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .card-form { display: block; }

        .card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 36px 24px 32px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            width: 100%;
            color: #fff;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            opacity: 0;
            transition: opacity 0.25s;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 48px rgba(0,0,0,0.5);
        }
        .card:active { transform: translateY(-1px); }

        /* Telegram card */
        .card.tg {
            border-color: rgba(42,171,238,0.2);
        }
        .card.tg::before {
            background: radial-gradient(ellipse at 50% 0%, rgba(42,171,238,0.12) 0%, transparent 70%);
        }
        .card.tg:hover {
            border-color: rgba(42,171,238,0.5);
        }
        .card.tg:hover::before { opacity: 1; }

        /* WhatsApp card */
        .card.wa {
            border-color: rgba(37,211,102,0.2);
        }
        .card.wa::before {
            background: radial-gradient(ellipse at 50% 0%, rgba(37,211,102,0.12) 0%, transparent 70%);
        }
        .card.wa:hover {
            border-color: rgba(37,211,102,0.5);
        }
        .card.wa:hover::before { opacity: 1; }

        .card-icon {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        .card.tg .card-icon {
            background: linear-gradient(135deg, #2AABEE 0%, #1a8cc7 100%);
            box-shadow: 0 8px 24px rgba(42,171,238,0.3);
        }
        .card.wa .card-icon {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            box-shadow: 0 8px 24px rgba(37,211,102,0.3);
        }

        /* SVG icons inline */
        .card-icon svg {
            width: 38px;
            height: 38px;
            fill: #fff;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }
        .card-desc {
            font-size: 12.5px;
            color: rgba(255,255,255,0.4);
            line-height: 1.5;
            position: relative;
            z-index: 1;
        }

        .card-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 8px;
        }
        .card.tg .card-badge {
            background: rgba(42,171,238,0.15);
            color: #2AABEE;
            border: 1px solid rgba(42,171,238,0.3);
        }
        .card.wa .card-badge {
            background: rgba(37,211,102,0.15);
            color: #25D366;
            border: 1px solid rgba(37,211,102,0.3);
        }

        .footer-note {
            text-align: center;
            margin-top: 28px;
            font-size: 12px;
            color: rgba(255,255,255,0.2);
        }

        @media (max-width: 480px) {
            .cards { grid-template-columns: 1fr; }
            .header h1 { font-size: 22px; }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="header">
        <div class="brand">⚡ Social CRM</div>
        <h1>Welcome back, <strong>{{ Auth::user()->name }}</strong></h1>
        <p>Choose your messaging channel to continue</p>
    </div>

    <div class="cards">
        {{-- Telegram --}}
        <form class="card-form" method="POST" action="{{ route('staff.setChannel') }}">
            @csrf
            <input type="hidden" name="channel" value="telegram">
            <button type="submit" class="card tg">
                <span class="card-badge">MTProto</span>
                <div class="card-icon">
                    {{-- Telegram paper plane SVG --}}
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>
                    </svg>
                </div>
                <div class="card-title">TG Cloud</div>
                <div class="card-desc">Telegram accounts &amp; conversations</div>
            </button>
        </form>

        {{-- WhatsApp --}}
        <form class="card-form" method="POST" action="{{ route('staff.setChannel') }}">
            @csrf
            <input type="hidden" name="channel" value="whatsapp">
            <button type="submit" class="card wa">
                <span class="card-badge">Business</span>
                <div class="card-icon">
                    {{-- WhatsApp SVG --}}
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </div>
                <div class="card-title">WP Cloud</div>
                <div class="card-desc">WhatsApp accounts &amp; conversations</div>
            </button>
        </form>
    </div>

    <div class="footer-note">Your session is secured · All messages are handled privately</div>
</div>

</body>
</html>