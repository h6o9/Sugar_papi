<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Payment Received' }}</title>
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
        .icon { color: #ed8936; font-size: 64px; margin-bottom: 20px; }
        h1 { color: #2d3748; font-size: 24px; margin-bottom: 10px; margin-top: 0; }
        p { color: #718096; margin-bottom: 20px; line-height: 1.5; }
        .reference {
            background: #fffaf0;
            border: 1px solid #fbd38d;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 12px;
            color: #c05621;
            word-break: break-all;
            margin-bottom: 20px;
        }
        .support-note {
            font-size: 13px;
            color: #a0aec0;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .btn {
            background-color: #ed8936;
            color: white;
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
        <div class="icon">⏳</div>
        <h1>{{ $title ?? 'Payment Received' }}</h1>
        <p>{{ $message ?? 'Your payment was received. Our team will confirm your order shortly.' }}</p>

        @if(!empty($reference))
        <div class="reference">Payment Reference:<br><strong>{{ $reference }}</strong></div>
        @endif

        @if(!empty($show_support))
        <p class="support-note">Please save your payment reference above and contact our support team if your order does not appear in the app within 10 minutes.</p>
        @endif

        <button class="btn" onclick="window.ReactNativeWebView && window.ReactNativeWebView.postMessage(JSON.stringify({ event: 'payment_pending', message: {{ Js::from($message ?? '') }} })); window.history.back();">
            Go Back to App
        </button>
    </div>
</body>
</html>