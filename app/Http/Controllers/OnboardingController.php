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

        return redirect()->route($onboarding->state($request->user())['route']);
    }
}
