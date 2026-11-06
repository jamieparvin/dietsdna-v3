<aside class="guidance-notice bmi-summary" aria-label="Body mass index">
    <h2>BMI: {{ $bmi['value'] === null ? 'Not available' : number_format($bmi['value'], 1) }}</h2>
    @include('partials.bmi-context')
</aside>
