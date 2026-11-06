<?php

namespace App\Http\Middleware;

use App\Services\Onboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function __construct(private readonly Onboarding $onboarding) {}

    public function handle(Request $request, Closure $next): Response
    {
        $setup = $this->onboarding->state($request->user());
        if (! $setup['complete']) {
            $message = 'Finish your nutrition profile and save your food preferences before using this feature.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'setup_url' => route($setup['route'])], 403);
            }

            return redirect()->route($setup['route'])->with('status', $message);
        }

        return $next($request);
    }
}
