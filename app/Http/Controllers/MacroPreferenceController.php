<?php

namespace App\Http\Controllers;

use App\Services\Nutrition\MacroSplit;
use App\Services\Nutrition\NutritionCalculatorSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MacroPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $rules = [
            'macro_mode' => ['required', Rule::in(['goal', 'custom'])],
            'profile_goal' => ['required', Rule::in(array_keys(NutritionCalculatorSettings::macroGoals()))],
        ];
        foreach (MacroSplit::LIMITS as $macro => [$min, $max]) {
            $rules['macro_'.$macro.'_percent'] = [
                'exclude_unless:macro_mode,custom', 'required', 'numeric',
                'between:'.$min.','.$max, 'decimal:0,2',
            ];
        }
        $data = $request->validate($rules);
        if ($data['macro_mode'] === 'custom') {
            $split = [];
            foreach (array_keys(MacroSplit::LIMITS) as $macro) {
                $split[$macro] = $data['macro_'.$macro.'_percent'];
            }
            if (! MacroSplit::valid($split)) {
                throw ValidationException::withMessages(['macro_protein_percent' => 'Protein, carbohydrate and fat must total exactly 100%.']);
            }
        }
        DB::transaction(function () use ($request, $data) {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $profile = $request->user()->nutritionProfile()->lockForUpdate()->firstOrFail();
            if ($profile->goal !== $data['profile_goal']) {
                throw ValidationException::withMessages(['macro_mode' => 'Your saved goal changed. Reload your profile and review the split before saving.']);
            }
            $custom = $data['macro_mode'] === 'custom';
            $profile->fill([
                'macro_mode' => $data['macro_mode'],
                'macro_goal' => $custom ? $profile->goal : null,
                'macro_protein_percent' => $custom ? $data['macro_protein_percent'] : null,
                'macro_carbohydrate_percent' => $custom ? $data['macro_carbohydrate_percent'] : null,
                'macro_fat_percent' => $custom ? $data['macro_fat_percent'] : null,
            ])->save();
        });
        return redirect()->to(route('nutrition-profile.edit').'#macro-targets')
            ->with('status', 'Your macro preferences have been saved. Existing planned portions were not changed.');
    }
}
