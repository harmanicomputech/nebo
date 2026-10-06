<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden">
            <tr><td style="background:#1a1a1a;padding:20px 28px;color:#ffffff;font-weight:bold;font-size:18px"><img src="{{ asset('images/brand/nebo-stage-white.png') }}" alt="{{ $company }}" width="118" height="32" style="display:block;height:32px;width:auto;border:0"></td></tr>
            <tr><td style="padding:28px">
                <p style="font-size:18px;font-weight:bold;margin:0 0 12px">Your quotation is ready</p>
                <p style="margin:0 0 16px;line-height:1.5">Hello {{ $quotation->customer->name }}, here is our quotation for <strong>{{ $quotation->title }}</strong>. You can review every line and accept or decline it online.</p>
                <p style="margin:0 0 4px;color:#6d6d6d;font-size:12px;text-transform:uppercase;letter-spacing:2px">Total</p>
                <p style="margin:0 0 20px;font-size:22px;font-weight:bold">{{ $total }}</p>
                <p style="margin:0 0 24px"><a href="{{ $url }}" style="display:inline-block;background:#cc1f1f;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">View quotation</a></p>
                <p style="margin:0;color:#6d6d6d;font-size:12px">Reference {{ $quotation->reference }} · valid until {{ $quotation->valid_until->format('j F Y') }}</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
