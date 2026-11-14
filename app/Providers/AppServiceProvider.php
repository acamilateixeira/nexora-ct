<?php

declare(strict_types=1);

namespace App\Providers;

use App\Rules\UnpredictablePassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->rules([new UnpredictablePassword()]));

        RateLimiter::for('operacoes', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
