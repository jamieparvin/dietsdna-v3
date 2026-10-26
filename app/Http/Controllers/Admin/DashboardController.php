<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\User;
use App\Models\WeeklyMealDraft;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $customers = User::query()
            ->where('is_admin', false);

        $customerCount = (clone $customers)->count();

        $newCustomerCount = (clone $customers)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $activeIngredientCount = Ingredient::query()
            ->where('is_active', true)
            ->count();

        $inactiveIngredientCount = Ingredient::query()
            ->where('is_active', false)
            ->count();

        $missingNutritionCount = Ingredient::query()
            ->where('is_active', true)
            ->where(function ($query) {
                foreach ([
                    'energy_kcal',
                    'protein_g',
                    'carbohydrate_g',
                    'sugars_g',
                    'fat_g',
                    'saturates_g',
                    'fibre_g',
                    'salt_g',
                ] as $field) {
                    $query->orWhereNull($field);
                }
            })
            ->count();

        $draftCounts = WeeklyMealDraft::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $queuedCount = (int) $draftCounts->get('queued', 0);
        $generatingCount = (int) $draftCounts->get('generating', 0);
        $readyCount = (int) $draftCounts->get('ready', 0);

        // Historical failures, not necessarily unresolved requests.
        $failedLastSevenDays = WeeklyMealDraft::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        $recentCustomers = (clone $customers)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'email', 'created_at']);

        $recentDrafts = WeeklyMealDraft::query()
            ->leftJoin('users', 'users.id', '=', 'weekly_meal_drafts.user_id')
            ->orderByDesc('weekly_meal_drafts.created_at')
            ->orderByDesc('weekly_meal_drafts.id')
            ->limit(6)
            ->get([
                'weekly_meal_drafts.id',
                'weekly_meal_drafts.start_date',
                'weekly_meal_drafts.status',
                'weekly_meal_drafts.created_at',
                'users.name as customer_name',
            ]);

        return view('admin.dashboard', compact(
            'customerCount',
            'newCustomerCount',
            'activeIngredientCount',
            'inactiveIngredientCount',
            'missingNutritionCount',
            'queuedCount',
            'generatingCount',
            'readyCount',
            'failedLastSevenDays',
            'recentCustomers',
            'recentDrafts'
        ));
    }
}