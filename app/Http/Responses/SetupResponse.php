<?php

namespace App\Http\Responses;

use App\Services\Onboarding;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

class SetupResponse implements LoginResponse, RegisterResponse
{
    public function toResponse($request): Response
    {
        $registering = $request->routeIs('register.store');
        if ($request->wantsJson()) {
            return response()->json($registering ? '' : ['two_factor' => false], $registering ? 201 : 200);
        }
        $setup = app(Onboarding::class)->state($request->user());
        if ($registering || ! $setup['complete']) {
            $request->session()->forget('url.intended');

            return redirect()->route($setup['route']);
        }

        return redirect()->intended(route('dashboard'));
    }
}
