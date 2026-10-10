<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Social CRM</title>
</head>
<body style="margin:0;padding:0;background-color:#0f0f0f;font-family:Arial,Helvetica,sans-serif;color:#e5e5e5;">

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">

    <div style="width:100%;max-width:420px;background-color:#1a1a1a;border-radius:12px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,0.6);">

        <h1 style="margin:0 0 8px 0;font-size:24px;font-weight:700;color:#ffffff;text-align:center;">Social CRM</h1>
        <p style="margin:0 0 32px 0;font-size:14px;color:#9ca3af;text-align:center;">Sign in to your account</p>

        @if (!empty($errors) && $errors->any())
            <div style="background-color:#7f1d1d;border:1px solid #dc2626;color:#fecaca;padding:12px 16px;border-radius:8px;margin-bottom:24px;font-size:14px;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ url('/login') }}">
            @csrf

            <div style="margin-bottom:20px;">
                <label for="email" style="display:block;font-size:14px;font-weight:600;color:#d1d5db;margin-bottom:8px;">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    style="width:100%;padding:12px 16px;background-color:#111827;border:1px solid #374151;border-radius:8px;color:#f9fafb;font-size:15px;outline:none;box-sizing:border-box;"
                >
            </div>

            <div style="margin-bottom:24px;">
                <label for="password" style="display:block;font-size:14px;font-weight:600;color:#d1d5db;margin-bottom:8px;">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    style="width:100%;padding:12px 16px;background-color:#111827;border:1px solid #374151;border-radius:8px;color:#f9fafb;font-size:15px;outline:none;box-sizing:border-box;"
                >
            </div>

            <button
                type="submit"
                style="width:100%;padding:14px;background-color:#2563eb;color:#ffffff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:background-color 0.2s;"
                onmouseover="this.style.backgroundColor='#1d4ed8'"
                onmouseout="this.style.backgroundColor='#2563eb'"
            >
                Sign In
            </button>

        </form>

    </div>

</div>

</body>
</html>
