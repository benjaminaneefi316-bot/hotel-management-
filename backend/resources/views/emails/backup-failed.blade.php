<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Backup problem</title>
</head>
<body style="margin:0;padding:24px;background:#f5f3ef;font-family:'Segoe UI',Helvetica,Arial,sans-serif;color:#2b2b2b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.08);">
        <tr>
            <td style="background:#7f1d1d;padding:20px 26px;color:#fff;font-size:18px;font-weight:600;">
                Backup failure alert
            </td>
        </tr>
        <tr>
            <td style="padding:26px;">
                <p style="margin:0 0 16px;font-size:15px;">A database backup did not complete successfully.</p>
                <table role="presentation" cellpadding="6" cellspacing="0" style="font-size:14px;width:100%;">
                    <tr><td style="color:#6b7280;">Reference</td><td><strong>{{ $backup->reference }}</strong></td></tr>
                    <tr><td style="color:#6b7280;">File</td><td>{{ $backup->filename }}</td></tr>
                    <tr><td style="color:#6b7280;">Destination</td><td>{{ strtoupper($backup->driver) }} {{ $backup->bucket ? '('.$backup->bucket.')' : '' }}</td></tr>
                    <tr><td style="color:#6b7280;">Status</td><td>{{ $backup->status }}</td></tr>
                    <tr><td style="color:#6b7280;">Verification</td><td>{{ $backup->verification_status ?? 'n/a' }}</td></tr>
                    <tr><td style="color:#6b7280;">Started</td><td>{{ $backup->started_at?->toDateTimeString() }}</td></tr>
                </table>

                @if ($backup->error)
                    <div style="margin-top:20px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px;font-family:Consolas,monospace;font-size:12px;color:#7f1d1d;white-space:pre-wrap;">{{ $backup->error }}</div>
                @endif

                <p style="margin:20px 0 0;font-size:13px;color:#6b7280;">
                    Check the backup log in the admin panel and re-run a manual backup once the cause is fixed.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
