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
                $token = $request->header('X-Api-Token');
            }

            if (! is_string($token) || $token === '') {
                $authHeader = $request->header('Authorization')
                    ?? $request->server('HTTP_AUTHORIZATION')
                    ?? $request->server('REDIRECT_HTTP_AUTHORIZATION');

                if (is_string($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
                    $token = $matches[1];
                }
            }

            if (is_string($token) && $token !== '') {
                $user = User::query()
                    ->where('api_token_hash', hash('sha256', $token))
                    ->where('is_active', true)
                    ->first();

                if ($user) {
                    return $user;
                }
            }

            if (Auth::guard('web')->check()) {
                $webUser = Auth::guard('web')->user();
                if ($webUser && $webUser->is_active) {
                    return $webUser;
                }
            }

            return null;
        });
    }
}
