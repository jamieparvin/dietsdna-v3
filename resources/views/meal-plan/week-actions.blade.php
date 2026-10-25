<a class="pl-button" href="{{ route('weekly-meals.create', ['date' => $weekStart->toDateString(), 'mode' => 'replace']) }}">
    Generate replacement week
</a>
<a class="pl-button" href="{{ route('meal-plan.clear.create', ['date' => $weekStart->toDateString()]) }}">
    Clear this week
</a>
