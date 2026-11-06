<div class="pf-edit-body">
    <label for="calculation_sex">Sex used by the calorie equation <span class="pf-field-hint">(Required for setup)</span></label>

    <select id="calculation_sex" name="calculation_sex">
        <option value="">Choose an equation</option>
        <option
            value="female"
            @selected(old('calculation_sex', $profile?->calculation_sex) === 'female')
        >Female</option>
        <option
            value="male"
            @selected(old('calculation_sex', $profile?->calculation_sex) === 'male')
        >Male</option>
    </select>

    <p class="pf-note">
        This selects a coefficient in the resting-energy equation.
        If neither equation is appropriate, leave it blank and seek
        an individual estimate.
    </p>

    <label for="resistance_training">
        Do you regularly do resistance or strength training?
        <span class="pf-field-hint">(Required for setup)</span>
    </label>

    <select id="resistance_training" name="resistance_training">
        <option value="">Choose an answer</option>

        <option
            value="1"
            @selected((string) old(
                'resistance_training',
                $profile?->resistance_training === null
                    ? ''
                    : (int) $profile->resistance_training
            ) === '1')
        >Yes</option>

        <option
            value="0"
            @selected((string) old(
                'resistance_training',
                $profile?->resistance_training === null
                    ? ''
                    : (int) $profile->resistance_training
            ) === '0')
        >No</option>
    </select>

    @php
        $planningAnswer = \App\Services\Nutrition\PlanningSuitability::answer($profile?->planning_context, $profile?->planning_circumstances);
        if (session()->hasOldInput('planning_context')) {
            $previousAnswer = old('planning_context');
            $planningAnswer = is_string($previousAnswer) ? \App\Services\Nutrition\PlanningSuitability::answer($previousAnswer) : null;
        } elseif (session()->hasOldInput('planning_circumstances_present') || session()->hasOldInput('planning_circumstances')) {
            $previousSelections = old('planning_circumstances', []);
            $planningAnswer = is_array($previousSelections) ? \App\Services\Nutrition\PlanningSuitability::context($previousSelections) : null;
        }
        $planningErrors = collect($errors->get('planning_context'))->merge($errors->get('planning_circumstances'))->merge($errors->get('planning_circumstances.*'))->flatten();
    @endphp
    <fieldset class="pf-suitability" id="planning_context" aria-describedby="planning-context-help{{ $planningErrors->isNotEmpty() ? ' planning-context-error' : '' }}">
        <legend>Do you need specialist nutrition support? <span class="pf-field-hint">(Required for setup)</span></legend>
        <p class="pf-note" id="planning-context-help">DietsDNA provides standard meal planning for adults. Pregnancy, breastfeeding and medical dietary requirements are not currently supported.</p>
        <input type="hidden" name="planning_context" value="">
        <div class="pf-suitability-options">
            @foreach(\App\Services\Nutrition\PlanningSuitability::ANSWERS as $value => $label)
                <label for="planning-answer-{{ $value }}">
                    <input type="radio" id="planning-answer-{{ $value }}" name="planning_context" value="{{ $value }}" @checked($planningAnswer === $value) @if($planningErrors->isNotEmpty()) aria-invalid="true" @endif>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @if($planningErrors->isNotEmpty())
            <p class="pf-note" id="planning-context-error" role="alert">{{ $planningErrors->implode(' ') }}</p>
        @endif
    </fieldset>
    @if(in_array($planningAnswer, ['specialist_support', 'unsure'], true) && ($targets['message'] ?? '') !== \App\Services\Nutrition\PlanningSuitability::RESTRICTED_MESSAGE)
        <p class="pf-note" role="status">{{ \App\Services\Nutrition\PlanningSuitability::RESTRICTED_MESSAGE }}</p>
    @endif
</div>
@once
    <style>
        .pf-page .pf-suitability { min-width:0; max-width:100%; margin:18px 0 0; padding:0; border:0; }
        .pf-suitability legend { font-size:13px; font-weight:700; line-height:1.6; }
        .pf-suitability-options { display:grid; gap:4px; margin-top:8px; }
        .pf-page .pf-suitability-options label { display:flex; align-items:flex-start; gap:10px; min-width:0; min-height:44px; margin:0; padding:10px 0; box-sizing:border-box; line-height:1.6; cursor:pointer; }
        .pf-suitability-options label span { min-width:0; overflow-wrap:anywhere; }
        .pf-page .pf-suitability-options input { flex:0 0 18px; width:18px; height:18px; margin:2px 0 0; accent-color:#167451; }
        .pf-suitability-options input:focus-visible { outline:3px solid #a36e20; outline-offset:3px; }
    </style>
@endonce
