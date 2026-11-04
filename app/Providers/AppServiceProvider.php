<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

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
        Auth::viaRequest('api-token', function (Request $request): ?User {
            $token = $request->bearerToken();

            if (! is_string($token) || $token === '') {
                return null;
            }

            return User::query()
                ->where('api_token_hash', hash('sha256', $token))
                ->where('is_active', true)
                ->first();
        });
    }
}
