<div class="bmi-context">
@if($bmi['value'] !== null)
    <p>
        Based on {{ $bmi['source'] === 'entry' ? 'your weigh-in of' : 'profile weight' }}
        {{ \App\Services\Nutrition\WeightUnits::format($bmi['weight_kg']) }} lbs
        @if($bmi['date'])
            ({{ $bmi['source'] === 'entry' ? 'recorded' : 'profile last saved' }} {{ \Carbon\CarbonImmutable::parse($bmi['date'])->format('j M Y') }})
        @endif
        and height {{ number_format((float) $bmi['height_cm'], 1) }} cm.
    </p>
@else
    <p>Add a valid height to your nutrition profile and a valid weight to see BMI.</p>
@endif
<p>BMI is a general screening measure, not a diagnosis or a measure of body fat.</p>
</div>
