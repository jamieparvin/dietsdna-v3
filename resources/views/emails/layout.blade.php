<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>@yield('email_title', 'DietsDNA')</title>
</head>
<body style="margin:0;padding:0;background-color:#faf8f2;color:#23382e;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">@yield('preheader')</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#faf8f2;">
<tr><td align="center" style="padding:36px 16px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
<tr><td align="center" style="padding:0 0 26px;">
<a href="{{ url('/') }}" style="font-size:28px;font-weight:bold;letter-spacing:-1px;text-decoration:none;color:#153f32;">Diets<span style="color:#286449;">DNA</span></a>
</td></tr>
<tr><td style="background-color:#153f32;padding:30px 28px;border-radius:14px 14px 0 0;">
<p style="margin:0 0 12px;color:#dbeeb9;font-size:11px;line-height:1.5;letter-spacing:2px;text-transform:uppercase;">@yield('eyebrow')</p>
<h1 style="margin:0;color:#faf8f2;font-family:Georgia,'Times New Roman',serif;font-size:32px;line-height:1.2;font-weight:normal;">@yield('headline')</h1>
</td></tr>
<tr><td style="background-color:#fffefb;padding:30px 28px;border:1px solid #e2e7dc;border-top:0;border-radius:0 0 14px 14px;">
@yield('message')
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0;">
<tr><td bgcolor="#153f32" style="border-radius:7px;text-align:center;mso-padding-alt:15px 22px;">
<a href="{{ $actionUrl }}" style="display:inline-block;border:1px solid #153f32;border-radius:7px;padding:15px 22px;color:#ffffff;background-color:#153f32;font-size:14px;line-height:1.4;font-weight:bold;text-decoration:none;">@yield('button')</a>
</td></tr>
</table>
@yield('after_button')
<p style="margin:24px 0 0;font-size:14px;line-height:1.7;color:#23382e;">Best wishes,<br><strong>The DietsDNA team</strong></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-top:24px;">
<p style="margin:0;border-top:1px solid #e2e7dc;padding-top:20px;color:#6b786f;font-size:12px;line-height:1.7;">If the button doesn’t open, copy and paste this link into your browser:</p>
<p style="margin:8px 0 0;word-break:break-all;overflow-wrap:anywhere;font-size:12px;line-height:1.7;"><a href="{{ $actionUrl }}" style="color:#286449;text-decoration:underline;">{{ $actionUrl }}</a></p>
</td></tr></table>
</td></tr>
<tr><td align="center" style="padding:24px 12px;color:#6b786f;font-size:12px;line-height:1.8;">
Nutrition that fits your life.<br>
&copy; {{ date('Y') }} DietsDNA. All rights reserved.<br>
<a href="{{ url('/') }}" style="color:#286449;">Visit DietsDNA</a>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
