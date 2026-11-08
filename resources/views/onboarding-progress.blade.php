<style>
    .setup-progress { display:flex; flex-wrap:wrap; gap:12px; padding:0; margin:0 0 24px; list-style:none; }
    .setup-progress li { flex:1 1 180px; padding:14px 18px; border:1px solid #d5e2d8; border-radius:12px; background:#fff; line-height:1.5; }
    .setup-progress [aria-current="step"] { background:#e9f3ec; border-color:#356249; font-weight:700; }
    .setup-progress-title { display:block; min-height:3em; font-size:14px; font-weight:700; line-height:1.5; }
    .setup-progress-subtitle { display:block; margin-top:6px; color:#42634f; font-size:13px; font-weight:400; line-height:1.5; }
    .setup-progress-status { display:block; margin-top:6px; color:#42634f; font-size:12px; font-weight:600; line-height:1.5; }
    .setup-note { padding:18px; margin-bottom:22px; background:#f0f5f1; border:1px solid #d5e2d8; color:#284e3e; border-radius:12px; line-height:1.7; }
    .setup-note p { margin: 8px 0 0; color: inherit; }
    .setup-review { background:#fff8e9; border-color:#ead9af; color:#59451f; }
</style>
<nav aria-label="Getting started">
    <ol class="setup-progress">
        @foreach([
            1 => ['title' => 'Nutrition profile', 'subtitle' => 'Provide your measurements, goals and choose how many meals you’d like each day'],
            2 => ['title' => 'Food preferences', 'subtitle' => 'Choose the ingredients you like, dislike and what you’d prefer to avoid.'],
            3 => ['title' => 'Weekly meal preview', 'subtitle' => 'Review a week of suggested meals before adding them to your plan.'],
        ] as $number => $step)
            <li @if($setup['step'] === $number) aria-current="step" @endif>
                <span class="setup-progress-title">{{ $number }}. {{ $step['title'] }}</span>
                <small class="setup-progress-subtitle">{{ $step['subtitle'] }}</small>
                @if($number === 1 && $setup['profileComplete'])<small class="setup-progress-status">Details saved{{ $setup['targets']['status'] === 'review' ? ' - automatic planning unavailable' : '' }}</small>@endif
                @if($number === 2 && $setup['preferencesComplete'])<small class="setup-progress-status">Reviewed and saved</small>@endif

            </li>
        @endforeach
    </ol>
</nav>
@if($showSetupGuidance ?? true)
@if($setup['targets']['status'] === 'review')
    <div class="setup-note setup-review" role="status">
        <strong>Automatic planning unavailable</strong>
        <p>{{ $setup['targets']['message'] }}</p>
        <p>You can save your preferences and finish setup. Completing setup does not make automatic planning available. Keep your answers accurate.</p>
    </div>
@elseif(!$setup['profileComplete'])
    <div class="setup-note">
        <p>Tell us about yourself, your goals and how many meals you’d like each day. We’ll use these details to estimate your daily calorie and macro targets. You can save your progress and return later.</p>
        @if(request()->routeIs('nutrition-profile.edit') && $profile !== null)
            <p role="status">{{ $setup['targets']['status'] === 'incomplete' ? $setup['targets']['message'] : 'Choose your full meals and optional snacks to complete your meal pattern.' }}</p>
        @endif
    </div>
@endif
@endif

