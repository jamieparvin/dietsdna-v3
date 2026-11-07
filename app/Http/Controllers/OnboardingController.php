<?php

namespace App\Http\Controllers;

use App\Services\Onboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __invoke(Request $request, Onboarding $onboarding): RedirectResponse
    {
        $request->session()->keep('status');

        $setup = $onboarding->state($request->user());
        $route = $setup['complete'] && $onboarding->generationBlock($request->user()) !== null ? 'dashboard' : $setup['route'];

        return redirect()->route($route);
    }
}
