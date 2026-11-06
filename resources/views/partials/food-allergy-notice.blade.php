<aside class="guidance-notice guidance-allergy" aria-label="Important: food allergies">
    <h2>Important: food allergies</h2>
    <p>DietsDNA does not currently support meal planning for food allergies. Marking a food as disliked or excluded does not make a plan allergy-safe. If you have a food allergy, do not rely on generated meals to manage it.</p>
    @if($restrictionMessage ?? null)
        <p role="status"><strong>{{ $restrictionMessage }}</strong></p>
    @endif
</aside>
