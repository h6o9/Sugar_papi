<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
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
        .icon { color: #48bb78; font-size: 64px; margin-bottom: 20px; }
        h1 { color: #2d3748; font-size: 24px; margin-bottom: 10px; margin-top: 0; }
        p { color: #718096; margin-bottom: 20px; line-height: 1.5; }
        .order-box {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
        }
        .order-box .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #68d391;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .order-box .order-code {
            font-size: 22px;
            font-weight: 700;
            color: #276749;
            letter-spacing: 0.05em;
        }
        .order-box .order-meta {
            font-size: 13px;
            color: #48bb78;
            margin-top: 6px;
        }
        .btn {
            background-color: #48bb78;
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
        <div class="icon">✓</div>
        <h1>Order Placed!</h1>
        <p>{{ $message ?? 'Your payment was successful and your order has been placed.' }}</p>

        @if(!empty($order))
        <div class="order-box">
            <div class="label">Order Number</div>
            <div class="order-code">#{{ $order->code }}</div>
            <div class="order-meta">
                Status: {{ $order->status ?? 'Pending' }}
                @if(!empty($order->total_amount))
                &nbsp;·&nbsp; Total: £{{ number_format($order->total_amount, 2) }}
                @endif
            </div>
        </div>
        @endif

        <button class="btn" onclick="
            window.ReactNativeWebView && window.ReactNativeWebView.postMessage(JSON.stringify({
                event: 'payment_success',
                order_id: {{ $order->id ?? 'null' }},
                order_code: '{{ $order->code ?? '' }}'
            }));
            window.close();
        ">
            Done
        </button>
    </div>

    <script>
        // Auto-notify app on page load (backup in case button not tapped)
        (function () {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    event: 'payment_success',
                    order_id: {{ $order->id ?? 'null' }},
                    order_code: '{{ $order->code ?? '' }}'
                }));
            }
        })();
    </script>
</body>
</html>