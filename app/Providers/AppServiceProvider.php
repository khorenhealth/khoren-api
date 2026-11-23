<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Scramble docs are local-only by default; allow them in production too.
        // Tighten later (auth, IP allowlist) if the OpenAPI UI should not be public.
        Gate::define('viewApiDocs', fn ($user = null): bool => true);

        RateLimiter::for('email-verification', function (Request $request): Limit {
            return Limit::perMinute(10)->by(Str::transliterate(
                Str::lower($request->string('email')->toString()).'|'.$request->ip()
            ));
        });
    }
}
