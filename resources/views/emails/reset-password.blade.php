@extends('emails.layout')
@section('email_title', 'Reset your DietsDNA password')
@section('preheader', 'A link to choose a new password for your DietsDNA account.')
@section('eyebrow', 'Your DietsDNA account')
@section('headline', 'A fresh password. Back to your plan.')
@section('button', 'Reset your password')
@section('message')
<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#23382e;">Hello {{ $name ?: 'there' }},</p>
<p style="margin:0;font-size:15px;line-height:1.8;color:#6b786f;">We received a request to reset the password for your DietsDNA account. Use the button below to choose a new one.</p>
@endsection
@section('after_button')
<p style="margin:0 0 16px;padding:14px 16px;background-color:#eef3e6;border-radius:8px;font-size:14px;line-height:1.7;color:#153f32;">This reset link expires in <strong>{{ $expires }} minutes</strong>.</p>
<p style="margin:0;font-size:13px;line-height:1.8;color:#6b786f;">If you didn’t request this, you can ignore this email. Your password will stay the same.</p>
@endsection
