@php($estimatedMinutes = \App\Services\Meals\MealPreparationTime::total($meal))
@if($estimatedMinutes !== null)
    <p class="guidance-estimate">Estimated total time: {{ $estimatedMinutes }} minutes ({{ $meal['estimated_prep_minutes'] }} preparation + {{ $meal['estimated_cook_minutes'] }} cooking). AI estimate; actual time may vary with portions and equipment.</p>
@else
    <p class="guidance-estimate">Time unavailable</p>
@endif
@if($estimatedMinutes !== null && isset($preferredMealMinutes) && $estimatedMinutes > $preferredMealMinutes)
    <p class="guidance-estimate" role="status">This meal may take longer than your preferred time.</p>
@endif
