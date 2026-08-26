<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="font-family: sans-serif; background: #f4f4f4; padding: 30px;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff;
                border-radius: 8px; padding: 32px;">
        <h2 style="color: #1a1a1a; margin-top: 0;">HanapBuhay</h2>
        <p style="color: #444;">Your one-time verification code is:</p>
        <div style="font-size: 36px; font-weight: bold; letter-spacing: 8px;
                    color: #2563eb; margin: 24px 0;">
            {{ $code }}
        </div>
        <p style="color: #666; font-size: 14px;">
            This code expires in <strong>10 minutes</strong>.
            Do not share it with anyone.
        </p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
        <p style="color: #999; font-size: 12px;">
            If you did not request this code, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
