@php
    $macroConfiguration = app(\App\Services\Nutrition\NutritionCalculatorSettings::class)->current();
    $macroCalculator = new \App\Services\Nutrition\NutritionTargetCalculator($macroConfiguration);
    $macroTargets = $macroCalculator->calculate($profile);
    $macroBase = $macroCalculator->calculateUsingGoalDefaults($profile);
    $macroGoalLabels = \App\Services\Nutrition\NutritionCalculatorSettings::macroGoals();
    $macroGoal = $profile?->goal;
    $macroHasGoal = isset($macroGoalLabels[$macroGoal ?? '']);
    $macroDefaults = $macroHasGoal
        ? \App\Services\Nutrition\NutritionCalculatorSettings::goalSplit($macroConfiguration['values'], $macroGoal)
        : ['protein' => 20, 'carbohydrate' => 50, 'fat' => 30];
    $macroMode = old('macro_mode', $profile?->macro_mode ?? 'goal');
    $macroErrorFields = ['macro_mode', 'profile_goal', 'macro_protein_percent', 'macro_carbohydrate_percent', 'macro_fat_percent'];
    $macroHasErrors = collect($macroErrorFields)->contains(fn ($field) => $errors->has($field));
    $macroReady = ($macroBase['status'] ?? '') === 'ready';
    $macroInitial = [];
    foreach (['protein', 'carbohydrate', 'fat'] as $macroName) {
        $macroField = 'macro_'.$macroName.'_percent';
        $macroInitial[$macroName] = old($macroField,
            $macroMode === 'custom' ? ($profile?->{$macroField} ?? $macroDefaults[$macroName]) : $macroDefaults[$macroName]
        );
    }
@endphp

<style>
    .mc-card { margin-top:24px; scroll-margin-top:85px; }
    .mc-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
    .mc-stat { padding:16px; border-radius:12px; background:#eef4ec; min-width:0; }
    .mc-stat span { display:block; color:#607766; font-size:13px; margin-bottom:6px; }
    .mc-stat strong { color:#153f32; font-size:22px; }
    .mc-card .mc-button { min-height:46px; padding:12px 18px; border:1px solid #204d3c; border-radius:10px; background:#204d3c; color:#fff; font:inherit; font-weight:600; cursor:pointer; }
    .mc-card .mc-secondary { background:#edf4e9; color:#204d3c; border-color:#ccdccc; }
    .mc-actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:20px; }
    .mc-alert { padding:12px 15px; border-radius:10px; background:#fff2e7; color:#704c2e; line-height:1.7; }
    .mc-error { color:#9b3030; }
    .mc-card label { display:block; margin:16px 0 8px; }
    .mc-card output { display:block; font-size:14px; padding:10px 0; }
    .mc-card input:disabled { background:#f0f3ed; color:#526558; }
    .mc-math { margin-top:20px; padding:16px; background:#f5f8f2; border-radius:12px; line-height:1.8; }
    .mc-math p { margin:6px 0; }
    .mc-total { margin:12px 0 0; font-weight:700; }
    .mc-card button:focus-visible, .mc-card input:focus-visible, .mc-card select:focus-visible { outline:3px solid #bc9148; outline-offset:3px; }
    @media(max-width:600px) {
        .mc-grid { grid-template-columns:1fr; }
        .mc-actions { flex-direction:column; }
        .mc-actions button { width:100%; }
    }
</style>

<section class="pf-card mc-card" id="macro-targets" aria-labelledby="macro-targets-heading">
    <div class="pf-card-head">
        <span class="pf-number" aria-hidden="true">%</span>
        <div>
            <h2 id="macro-targets-heading">Your macro targets</h2>
            <p class="pf-description">See the calculation and choose your own split</p>
        </div>
    </div>

    @if(($macroTargets['status'] ?? '') === 'ready')
        <p class="pf-note">
            {{ $macroTargets['macro_source'] === 'custom' ? 'Your saved custom split' : 'Your goal’s default split' }}
            · {{ $macroGoalLabels[$macroGoal] }}
            · {{ number_format($macroTargets['calories_kcal']) }} kcal per day
        </p>
        <div class="mc-grid">
            @foreach(['protein' => 'Protein', 'carbohydrate' => 'Carbohydrate allocation', 'fat' => 'Fat'] as $macroName => $macroLabel)
                <div class="mc-stat">
                    <span>{{ $macroLabel }} · {{ number_format($macroTargets['macro_percentages'][$macroName], 2) }}%</span>
                    <strong>{{ number_format($macroTargets[$macroName.'_g'], 1) }} g</strong>
                </div>
            @endforeach
        </div>
        <p class="pf-note">
            Protein equivalent: {{ number_format($macroTargets['protein_factor'], 2) }} g/kg of your saved weight.
            This is the result of the percentage split, not a separate weight-based protein rule.
        </p>
    @else
        <p class="mc-alert">{{ $macroTargets['message'] }}</p>
    @endif

    <p class="pf-note">
        Percentages are shares of calories, not shares of food weight.
        These are editable planning presets. Food total carbohydrate includes fibre;
        the meal planner compares an estimated available-carbohydrate figure with this allocation.
    </p>

    @if($macroHasGoal)
        <details class="pf-edit" id="macro-editor" @if($macroHasErrors || ($profile?->macro_mode === 'custom' && $profile?->macro_goal !== $macroGoal)) open @endif>
            <summary>View calculation and adjust macros</summary>
            <div class="pf-edit-body">
                @if($macroReady)
                    <div class="mc-math">
                        <strong>How your calories are estimated</strong>
                        <p>Resting energy = 10 × weight (kg) + 6.25 × height (cm) − 5 × age + the calculation-sex constant (+5 male, −161 female).</p>
                        <p>Your inputs: 10 × {{ number_format($profile->weight_kg, 2) }} + 6.25 × {{ number_format($profile->height_cm, 2) }} − 5 × {{ $macroBase['age'] }} {{ $profile->calculation_sex === 'male' ? '+ 5' : '− 161' }} = {{ number_format($macroBase['resting_kcal'], 1) }} kcal.</p>
                        <p>{{ number_format($macroBase['resting_kcal'], 1) }} × activity factor {{ $macroBase['activity_factor'] }} = {{ number_format($macroBase['maintenance_kcal'], 1) }} kcal estimated maintenance.</p>
                        <p>After the goal adjustment and rounding: <strong>{{ number_format($macroBase['calories_kcal']) }} kcal/day</strong> ({{ $macroBase['adjustment_kcal'] >= 0 ? '+' : '−' }}{{ number_format(abs($macroBase['adjustment_kcal']), 1) }} kcal compared with estimated maintenance).</p>
                    </div>
                @endif

                <div class="mc-math">
                    <strong>How percentages become grams</strong>
                    <p>Protein grams = daily calories × protein % ÷ 100 ÷ 4.</p>
                    <p>Carbohydrate allocation grams = daily calories × carbohydrate % ÷ 100 ÷ 4.</p>
                    <p>Fat grams = daily calories × fat % ÷ 100 ÷ 9.</p>
                    <p>The three percentages must total 100%. Displayed grams are rounded; calculations use the unrounded values.</p>
                </div>

                <p class="pf-note">
                    Save any measurement or goal changes in the profile form above first.
                    This form saves only your macro preferences. Changing macros does not change your calorie target.
                </p>

                @if($profile?->macro_mode === 'custom' && $profile?->macro_goal !== $macroGoal)
                    <p class="mc-alert">Your goal changed. Review the percentages and save them for your current goal, or choose its defaults below.</p>
                @endif

                <form method="POST" action="{{ route('nutrition-profile.macros.update') }}" id="macro-preferences-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="profile_goal" value="{{ $macroGoal }}">

                    @foreach($macroErrorFields as $macroErrorField)
                        @error($macroErrorField)
                            <p class="mc-error" role="alert">{{ $message }}</p>
                        @enderror
                    @endforeach

                    <label for="macro_mode">Macro settings</label>
                    <select id="macro_mode" name="macro_mode">
                        <option value="goal" @selected($macroMode === 'goal')>Use goal defaults</option>
                        <option value="custom" @selected($macroMode === 'custom')>Use my custom split</option>
                    </select>

                    <p class="pf-note">
                        Goal defaults: {{ $macroDefaults['protein'] }}% protein,
                        {{ $macroDefaults['carbohydrate'] }}% carbohydrate,
                        {{ $macroDefaults['fat'] }}% fat.
                        Defaults follow future admin changes; a custom split stays yours until you change or reset it.
                    </p>

                    <div class="mc-grid">
                        @foreach(['protein' => 'Protein', 'carbohydrate' => 'Carbohydrate', 'fat' => 'Fat'] as $macroName => $macroLabel)
                            @php([$macroMin, $macroMax] = \App\Services\Nutrition\MacroSplit::LIMITS[$macroName])
                            <div>
                                <label for="macro_{{ $macroName }}_percent">{{ $macroLabel }} (%)</label>
                                <input id="macro_{{ $macroName }}_percent" name="macro_{{ $macroName }}_percent"
                                    type="number" inputmode="decimal" min="{{ $macroMin }}" max="{{ $macroMax }}"
                                    step="0.01" required value="{{ $macroInitial[$macroName] }}"
                                    data-macro="{{ $macroName }}" aria-describedby="macro-{{ $macroName }}-preview">
                                <output id="macro-{{ $macroName }}-preview">Live gram preview appears here.</output>
                                <span class="pf-note">This version allows {{ $macroMin }}–{{ $macroMax }}%.</span>
                            </div>
                        @endforeach
                    </div>

                    <p class="mc-total" id="macro-total" role="status">Choose a split totalling 100%.</p>
                    <p class="pf-note">The live figures are an unsaved preview. Save to update your targets, then generate a new weekly preview. Existing saved meals and planned portions retain their original quantities.</p>
                    <p class="mc-error" id="macro-unsaved-goal" hidden>Save your goal change in the profile form above before saving macros.</p>

                    <div class="mc-actions">
                        <button type="submit" class="mc-button" id="macro-save">Save macro preferences</button>
                        <button type="button" class="mc-button mc-secondary" id="macro-reset">Reset to goal defaults</button>
                    </div>
                    <p class="pf-note">Reset selects the defaults. Press Save macro preferences to apply it.</p>
                </form>
            </div>
        </details>
    @else
        <p class="pf-note">Choose and save a goal above to set your macro preferences.</p>
    @endif
</section>

<script>
(() => {
    const form = document.getElementById('macro-preferences-form');
    if (!form) return;
    const mode = document.getElementById('macro_mode');
    const inputs = [...form.querySelectorAll('[data-macro]')];
    const defaults = @json($macroDefaults);
    const calories = @json($macroReady ? $macroBase['calories_kcal'] : null);
    const savedGoal = @json($macroGoal);
    const goalInput = document.getElementById('goal');
    const save = document.getElementById('macro-save');
    let previousMode = mode.value;
    let custom = Object.fromEntries(inputs.map(input => [input.dataset.macro, input.value]));

    function render() {
        let hundredths = 0;
        let valid = true;
        inputs.forEach(input => {
            const value = Number(input.value);
            const usable = input.value.trim() !== '' && Number.isFinite(value);
            valid = valid && usable && value >= Number(input.min) && value <= Number(input.max)
                && Math.abs(value * 100 - Math.round(value * 100)) < 0.000001;
            if (usable) hundredths += Math.round(value * 100);
            const divisor = input.dataset.macro === 'fat' ? 9 : 4;
            const output = document.getElementById(`macro-${input.dataset.macro}-preview`);
            output.textContent = calories !== null && usable
                ? `${(calories * value / 100 / divisor).toFixed(1)} g · ${(calories * value / 100).toFixed(0)} kcal`
                : 'Complete your saved profile to preview grams.';
        });
        const wrongGoal = goalInput && goalInput.value !== savedGoal;
        document.getElementById('macro-unsaved-goal').hidden = !wrongGoal;
        document.getElementById('macro-total').textContent =
            `Total: ${(hundredths / 100).toFixed(2)}%${valid && hundredths === 10000 ? ' — ready to save' : ' — must be 100% within the allowed ranges'}`;
        save.disabled = wrongGoal || (mode.value === 'custom' && (!valid || hundredths !== 10000));
    }

    function applyMode() {
        const isCustom = mode.value === 'custom';
        inputs.forEach(input => {
            input.disabled = !isCustom;
            input.value = isCustom ? custom[input.dataset.macro] : defaults[input.dataset.macro];
        });
        render();
    }
    mode.addEventListener('change', () => {
        if (previousMode === 'custom') custom = Object.fromEntries(inputs.map(input => [input.dataset.macro, input.value]));
        previousMode = mode.value;
        applyMode();
    });
    inputs.forEach(input => input.addEventListener('input', render));
    goalInput?.addEventListener('change', render);
    document.getElementById('macro-reset').addEventListener('click', () => {
        mode.value = 'goal'; previousMode = 'goal'; applyMode();
    });
    form.addEventListener('submit', () => { save.disabled = true; save.textContent = 'Saving…'; });
    if (window.location.hash === '#macro-targets') document.getElementById('macro-editor').open = true;
    form.addEventListener('invalid', () => { document.getElementById('macro-editor').open = true; }, true);
    applyMode();
})();
</script>
