<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your verification code</title>
</head>
<body style="margin:0;padding:0;background:#f5f3ef;font-family:'Segoe UI',Helvetica,Arial,sans-serif;color:#2b2b2b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3ef;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.08);">
                <tr>
                    <td style="background:#1f2933;padding:26px 32px;">
                        <div style="color:#c9a227;font-size:12px;letter-spacing:3px;text-transform:uppercase;">{{ config('app.name') }}</div>
                        <div style="color:#ffffff;font-size:20px;font-weight:600;margin-top:6px;">Two-factor verification</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 16px;font-size:15px;">Hello {{ $name }},</p>
                        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;">
                            Use the one-time code below to finish signing in. It expires in
                            <strong>{{ $expiresInMinutes }} minutes</strong> and can only be used once.
                        </p>

                        <div style="background:#faf8f4;border:1px dashed #c9a227;border-radius:10px;padding:20px;text-align:center;margin:0 0 24px;">
                            <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#7a7364;margin-bottom:8px;">Verification code</div>
                            <div style="font-size:34px;font-weight:700;letter-spacing:8px;color:#1f2933;">{{ $code }}</div>
                        </div>

                        <p style="margin:0 0 8px;font-size:13px;color:#6b7280;line-height:1.6;">
                            If you did not try to sign in, ignore this email and consider changing your password.
                            Never share this code with anyone - hotel staff will never ask you for it.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#f5f3ef;padding:18px 32px;font-size:12px;color:#8a8578;">
                        This is an automated message. Please do not reply.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
