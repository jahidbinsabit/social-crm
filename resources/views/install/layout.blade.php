<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — Social CRM</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background-color: #0f0f0f; font-family: Arial, Helvetica, sans-serif; color: #e5e5e5; }
        .wrap { min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: 32px 16px 48px; }
        .card { width: 100%; max-width: 760px; background-color: #1a1a1a; border-radius: 12px; padding: 36px 40px; box-shadow: 0 4px 24px rgba(0,0,0,0.6); }
        h1 { margin: 0 0 6px 0; font-size: 24px; font-weight: 700; color: #ffffff; text-align: center; }
        .sub { margin: 0 0 28px 0; font-size: 14px; color: #9ca3af; text-align: center; }
        h2 { margin: 0 0 6px 0; font-size: 19px; color: #ffffff; }
        .stepdesc { margin: 0 0 24px 0; font-size: 14px; color: #9ca3af; }
        .steps { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 30px 0; padding: 0; list-style: none; }
        .steps li { flex: 1 1 auto; text-align: center; font-size: 11.5px; padding: 8px 4px; border-radius: 8px; background: #111827; color: #6b7280; border: 1px solid #1f2937; }
        .steps li.active { background: #1d4ed8; color: #ffffff; border-color: #2563eb; font-weight: 700; }
        .steps li.done { background: #14532d; color: #bbf7d0; border-color: #166534; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 22px; font-size: 14px; }
        .alert-error { background-color: #7f1d1d; border: 1px solid #dc2626; color: #fecaca; }
        .alert-status { background-color: #14532d; border: 1px solid #16a34a; color: #bbf7d0; }
        .alert ul { margin: 0; padding-left: 18px; }
        label { display: block; font-size: 14px; font-weight: 600; color: #d1d5db; margin-bottom: 8px; }
        .hint { font-size: 12.5px; color: #9ca3af; margin-top: 6px; }
        input[type=text], input[type=email], input[type=password], input[type=number], input[type=url] {
            width: 100%; padding: 12px 16px; background-color: #111827; border: 1px solid #374151;
            border-radius: 8px; color: #f9fafb; font-size: 15px; outline: none;
        }
        input:focus { border-color: #2563eb; }
        input[readonly] { color: #9ca3af; }
        .field { margin-bottom: 20px; }
        .row { display: flex; gap: 16px; }
        .row .field { flex: 1; }
        .mono { font-family: Consolas, Menlo, monospace; font-size: 13.5px; }
        button.btn, a.btn {
            display: inline-block; background-color: #2563eb; color: #ffffff; border: none; cursor: pointer;
            padding: 12px 28px; border-radius: 8px; font-size: 15px; font-weight: 700; text-decoration: none; text-align: center;
        }
        button.btn:hover, a.btn:hover { background-color: #1d4ed8; }
        button.btn:disabled { background-color: #374151; color: #9ca3af; cursor: not-allowed; }
        a.btn-secondary { background-color: #1f2937; color: #d1d5db; border: 1px solid #374151; }
        a.btn-secondary:hover { background-color: #111827; }
        .actions { display: flex; justify-content: space-between; align-items: center; margin-top: 28px; gap: 12px; }
        table.req { width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 8px; }
        table.req th, table.req td { text-align: left; padding: 9px 10px; border-bottom: 1px solid #262626; vertical-align: top; }
        table.req th { color: #9ca3af; font-weight: 600; font-size: 12.5px; text-transform: uppercase; letter-spacing: .04em; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge-ok { background: #14532d; color: #bbf7d0; }
        .badge-fail { background: #7f1d1d; color: #fecaca; }
        .badge-warn { background: #713f12; color: #fde68a; }
        dl.review { display: grid; grid-template-columns: 210px 1fr; gap: 9px 16px; font-size: 14px; margin: 0 0 8px 0; }
        dl.review dt { color: #9ca3af; }
        dl.review dd { margin: 0; color: #f3f4f6; word-break: break-all; }
        pre.cmds {
            background: #0b1220; border: 1px solid #1f2937; border-radius: 8px; padding: 16px 18px;
            font-family: Consolas, Menlo, monospace; font-size: 13.5px; line-height: 1.65; color: #dbeafe;
            overflow-x: auto; white-space: pre; margin: 0 0 20px 0;
        }
        .note { background: #111827; border: 1px solid #1f2937; border-radius: 8px; padding: 14px 16px; font-size: 13.5px; color: #d1d5db; margin-bottom: 20px; line-height: 1.55; }
        .note strong { color: #ffffff; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Social CRM</h1>
        <p class="sub">Installation wizard</p>

        @php
            $stepLabels = [1 => 'Requirements', 2 => 'Database', 3 => 'Keys & Services', 4 => 'Admin', 5 => 'Review', 6 => 'Done'];
            $currentStep = $step ?? 1;
        @endphp
        <ol class="steps">
            @foreach ($stepLabels as $n => $label)
                <li class="{{ $n === $currentStep ? 'active' : ($n < $currentStep ? 'done' : '') }}">{{ $n }}. {{ $label }}</li>
            @endforeach
        </ol>

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @if (session('status'))
            <div class="alert alert-status">{{ session('status') }}</div>
        @endif

        @if (!empty($errors) && $errors->any())
            <div class="alert alert-error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</div>
</body>
</html>
