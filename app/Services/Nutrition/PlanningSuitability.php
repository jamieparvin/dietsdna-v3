<?php

namespace App\Services\Nutrition;

use App\Models\NutritionProfile;

class PlanningSuitability
{
    public const RESTRICTED_MESSAGE = 'Automatic calorie targets and meal generation aren’t available for your circumstances at present. A registered dietitian or your healthcare professional can help you establish suitable nutrition targets.';

    public const ANSWERS = [
        'general_adult' => 'No — none of these apply to me',
        'specialist_support' => 'Yes — one or more apply to me',
        'unsure' => 'I’m not sure',
    ];

    public const CIRCUMSTANCES = [
        'pregnant' => 'Pregnant',
        'breastfeeding' => 'Breastfeeding',
        'medical_dietary_needs' => 'Medical dietary needs',
        'not_sure' => 'Not sure',
        'none' => 'None apply',
    ];

    public static function answer(?string $context, ?array $circumstances = null): ?string
    {
        if ($circumstances === []) {
            return null;
        }
        if ($circumstances !== null && $circumstances !== ['none']) {
            return $circumstances === ['not_sure'] ? 'unsure' : 'specialist_support';
        }

        return match ($context) {
            'general_adult' => 'general_adult',
            'unsure' => 'unsure',
            'needs_review', 'specialist_support' => 'specialist_support',
            default => null,
        };
    }

    public static function label(?string $answer, ?array $circumstances = null): string
    {
        return self::ANSWERS[self::answer($answer, $circumstances)] ?? 'Not set';
    }

    public static function needsClarification(?NutritionProfile $profile): bool
    {
        return $profile?->planning_circumstances === null && in_array($profile?->planning_context, ['needs_review', 'specialist_support'], true);
    }

    public static function context(array $circumstances): ?string
    {
        return match ($circumstances) {
            [] => null,
            ['none'] => 'general_adult',
            ['not_sure'] => 'unsure',
            default => 'specialist_support',
        };
    }
}
