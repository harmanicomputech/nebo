<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden">
            <tr><td style="background:#1a1a1a;padding:20px 28px;color:#ffffff;font-weight:bold;font-size:18px">{{ $company }}</td></tr>
            <tr><td style="padding:28px">
                <p style="font-size:18px;font-weight:bold;margin:0 0 12px">We received your production request</p>
                <p style="margin:0 0 16px;line-height:1.5">Hello {{ $request->contact_person }}, thank you for telling us about <strong>{{ $request->event_name }}</strong>. Our production team will review your request and contact you with a tailored production solution and quotation.</p>
                <p style="margin:0 0 4px;color:#6d6d6d;font-size:12px;text-transform:uppercase;letter-spacing:2px">Your reference</p>
                <p style="margin:0 0 20px;font-family:monospace;font-size:20px;font-weight:bold">{{ $request->reference }}</p>
                <p style="margin:0 0 24px"><a href="{{ $trackUrl }}" style="display:inline-block;background:#cc1f1f;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">Track your request</a></p>
                <p style="margin:0;color:#6d6d6d;font-size:12px">Event date: {{ $request->event_date->format('j F Y') }} · Venue: {{ $request->venue }}</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
