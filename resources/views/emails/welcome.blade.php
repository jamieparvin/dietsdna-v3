@extends('emails.layout')
@section('email_title', 'Welcome to DietsDNA')
@section('preheader', 'Start with your nutrition profile, then build a plan around food you enjoy.')
@section('eyebrow', 'Welcome to DietsDNA')
@section('headline', 'Your food. Your goals. Your fresh start.')
@section('button', 'Build your nutrition profile')
@section('message')
<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#23382e;">Hello {{ $name ?: 'there' }},</p>
<p style="margin:0 0 24px;font-size:15px;line-height:1.8;color:#6b786f;">Your account is ready. Start building a nutrition routine around your goals, your preferences and the foods you enjoy.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ([
    ['01', 'Make it personal', 'Complete your nutrition profile to calculate your calorie, protein, carbohydrate and fat targets.'],
    ['02', 'Choose your favourites', 'Set your food preferences, explore meal ideas and save the meals you want to enjoy again.'],
    ['03', 'Plan your week', 'Generate a weekly preview, review it and confirm your meals. Use your shopping list and record your weight progress.'],
] as $step)
<tr>
<td width="44" valign="top" style="padding:12px 8px 12px 0;color:#286449;font-family:Georgia,serif;font-size:22px;">{{ $step[0] }}</td>
<td style="padding:12px 0;border-bottom:1px solid #e2e7dc;">
<p style="margin:0 0 5px;color:#153f32;font-size:15px;font-weight:bold;line-height:1.5;">{{ $step[1] }}</p>
<p style="margin:0;color:#6b786f;font-size:13px;line-height:1.8;">{{ $step[2] }}</p>
</td></tr>
@endforeach
</table>
@endsection
@section('after_button')
<p style="margin:0;font-size:13px;line-height:1.8;color:#6b786f;">You can return to your account whenever you’re ready. Start with your profile, then take it one meal at a time.</p>
@endsection
