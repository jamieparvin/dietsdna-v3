<p class="pf-note" id="profile-setup-help">
    Fields marked ‘Required for setup’ are needed to complete this step. Optional fields can be left blank. If you’re not ready to finish, you can save your progress and return later.
</p>
@foreach([
    ['id' => 'personal', 'title' => 'Personal details', 'description' => 'Your date of birth and measurements', 'view' => 'personal-fields'],
    ['id' => 'goals', 'title' => 'Goals and activity', 'description' => 'Your direction and daily routine', 'view' => 'goal-fields'],
    ['id' => 'pattern', 'title' => 'Meal pattern', 'description' => 'Full meals, optional snacks and time available', 'view' => 'meal-pattern-fields'],
    ['id' => 'calculation', 'title' => 'Calculation details', 'description' => 'Information used for your calorie and macro estimates', 'view' => 'calculation-fields'],
] as $section)
    <section class="pf-card" aria-labelledby="{{ $section['id'] }}-heading" aria-describedby="profile-setup-help">
        <div class="pf-card-head">
            <span class="pf-number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <div>
                <h2 id="{{ $section['id'] }}-heading">{{ $section['title'] }}</h2>
                <p class="pf-description">{{ $section['description'] }}</p>
            </div>
        </div>
        @include('nutrition-profile.'.$section['view'])
        @if($section['id'] === 'personal')
            <div>
                <strong>BMI · profile weight:</strong>
                <span data-bmi-value>{{ $bmi['value'] === null ? 'Not available' : number_format($bmi['value'], 1) }}</span>
                @include('partials.bmi-context')
            </div>
        @endif
    </section>
@endforeach
