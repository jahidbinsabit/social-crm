<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #0f0f0f;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background-color: #1a1a1a;
            border: 1px solid #333333;
            border-radius: 12px;
            padding: 48px 40px;
            text-align: center;
            max-width: 420px;
            width: 100%;
        }
        .code {
            font-size: 64px;
            font-weight: 700;
            color: #ef4444;
            line-height: 1;
            margin-bottom: 16px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .desc {
            font-size: 14px;
            color: #9ca3af;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .btn {
            display: inline-block;
            padding: 10px 24px;
            background-color: #3b82f6;
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
        }
        .btn:hover { background-color: #2563eb; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">500</div>
        <div class="title">Server Error</div>
        <p class="desc">Something went wrong on our end. Our team has been notified. Please try again in a moment.</p>
        <a href="/" class="btn">Back to Home</a>
    </div>
</body>
</html>
