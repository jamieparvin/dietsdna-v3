<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\MacroSplit;
use App\Services\Nutrition\NutritionCalculatorSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NutritionCalculatorSettingsController extends Controller
{
    public function edit(NutritionCalculatorSettings $settings)
    {
        $configuration = $settings->current();
        $history = DB::table('nutrition_calculator_setting_changes as changes')
            ->leftJoin('users', 'users.id', '=', 'changes.changed_by')
            ->select('changes.*', 'users.name as admin_name')
            ->orderByDesc('changes.revision')->limit(10)->get();
        return view('admin.calculator-settings.edit', [
            'configuration' => $configuration,
            'groups' => NutritionCalculatorSettings::fields(), 'history' => $history,
        ]);
    }

    public function update(Request $request)
    {
        $metadata = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'action' => ['required', Rule::in(['save', 'reset'])],
        ]);
        if ($metadata['action'] === 'reset') {
            $values = NutritionCalculatorSettings::defaults();
        } else {
            $rules = [];
            foreach (NutritionCalculatorSettings::fields() as $fields) {
                foreach ($fields as $key => $definition) {
                    $rules[$key] = ['required', 'numeric', 'between:'.$definition[2].','.$definition[3],
                        str_starts_with($key, 'macro_') ? 'decimal:0,2' : 'decimal:0,3'];
                }
            }
            $values = array_map(fn ($value) => (float) $value, $request->validate($rules));
        }
        if (! ($values['activity_sedentary'] < $values['activity_light']
            && $values['activity_light'] < $values['activity_moderate']
            && $values['activity_moderate'] < $values['activity_high'])) {
            throw ValidationException::withMessages(['activity_sedentary' => 'Activity multipliers must increase from mostly seated through light, moderate and very active.']);
        }
        foreach (NutritionCalculatorSettings::macroGoals() as $goal => $label) {
            if (! MacroSplit::valid(NutritionCalculatorSettings::goalSplit($values, $goal))) {
                throw ValidationException::withMessages(['macro_'.$goal.'_protein_percent' => $label.': protein, carbohydrate and fat must total exactly 100%.']);
            }
        }
        DB::transaction(function () use ($request, $metadata, $values) {
            $current = DB::table('nutrition_calculator_settings')->where('id', 1)->lockForUpdate()->first();
            abort_if(! $current, 500, 'Calculator settings are missing.');
            if ((int) $current->revision !== (int) $metadata['revision']) {
                throw ValidationException::withMessages(['revision' => 'Another administrator changed these settings. Reload the page before making further changes.']);
            }
            $revision = (int) $current->revision + 1;
            $parameters = json_encode($values, JSON_THROW_ON_ERROR);
            $now = now();
            DB::table('nutrition_calculator_settings')->where('id', 1)->update([
                'parameters' => $parameters, 'revision' => $revision, 'updated_at' => $now,
            ]);
            DB::table('nutrition_calculator_setting_changes')->insert([
                'revision' => $revision, 'parameters' => $parameters,
                'reason' => trim($metadata['reason']), 'changed_by' => $request->user()->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        return redirect()->route('admin.calculator-settings.edit')->with('status', 'Global calculator settings saved.');
    }
}
