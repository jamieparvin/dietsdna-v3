<?php

namespace App\Services\Nutrition;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WeightInput
{
    /** @param array<string, mixed> $storedKilograms */
    public function kilograms(Request $request, array $storedKilograms, bool $required = false): array
    {
        $data = $request->all();
        $rules = [];
        $messages = [];
        foreach ($storedKilograms as $kgField => $stored) {
            $lbsField = str_replace('_kg', '_lbs', $kgField);
            if ($request->exists($lbsField) && $request->exists($kgField)) {
                throw ValidationException::withMessages([$lbsField => 'Submit the weight in lbs or kg, not both.']);
            }
            if (! $request->exists($lbsField) && (! $required || $request->exists($kgField))) {
                continue;
            }
            $label = str_starts_with($kgField, 'target') ? 'Target weight' : 'Weight';
            $rules[$lbsField] = ['bail', $required ? 'required' : 'nullable', 'numeric', function (string $attribute, mixed $value, Closure $fail) use ($label): void {
                $kilograms = WeightUnits::kilograms((float) $value);
                if (! is_finite($kilograms) || $kilograms < WeightUnits::MIN_KG || $kilograms > WeightUnits::MAX_KG) {
                    $fail($label.' must be between '.WeightUnits::format(WeightUnits::MIN_KG).' and '.WeightUnits::format(WeightUnits::MAX_KG).' lbs.');
                }
            }];
            $messages[$lbsField.'.required'] = 'Enter your weight in lbs.';
            $messages[$lbsField.'.numeric'] = $label.' must be a number in lbs.';
        }
        $validated = Validator::make($data, $rules, $messages)->validate();
        foreach ($storedKilograms as $kgField => $stored) {
            $lbsField = str_replace('_kg', '_lbs', $kgField);
            if (! array_key_exists($lbsField, $validated)) {
                continue;
            }
            $pounds = $validated[$lbsField];
            // A rounded, unchanged form value must not erode the stored precision.
            $data[$kgField] = $pounds === null ? null : (
                $stored !== null && (float) $pounds === (float) WeightUnits::input($stored)
                    ? $stored
                    : WeightUnits::kilograms((float) $pounds)
            );
            unset($data[$lbsField]);
        }

        return $data;
    }
}
