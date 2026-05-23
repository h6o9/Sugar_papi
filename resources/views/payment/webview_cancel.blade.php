<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Payment Cancelled' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f7fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            text-align: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        .icon { color: #f56565; font-size: 64px; margin-bottom: 20px; }
        h1 { color: #2d3748; font-size: 24px; margin-bottom: 10px; margin-top: 0; }
        p { color: #718096; margin-bottom: 20px; line-height: 1.5; }
        .reference {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 12px;
            color: #a0aec0;
            word-break: break-all;
            margin-bottom: 20px;
        }
        .btn {
            background-color: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✕</div>
        <h1>{{ $title ?? 'Payment Cancelled' }}</h1>
        <p>{{ $message ?? 'You have cancelled the payment process. No charges were made.' }}</p>

        @if(!empty($reference))
        <div class="reference">Reference: {{ $reference }}</div>
        @endif

        <button class="btn" onclick="window.ReactNativeWebView && window.ReactNativeWebView.postMessage(JSON.stringify({ event: 'payment_cancel', message: {{ Js::from($message ?? '') }} })); window.history.back();">
            Go Back
        </button>
    </div>
</body>
</html>