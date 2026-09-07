<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        Gate::define('access-imports', function (User $user) {
            return in_array($user->role, ['admin', 'staff'], true);
        });

        Gate::define('access-catalog', function (User $user) {
            return in_array($user->role, ['coordinator', 'staff', 'admin'], true);
        });

        Gate::define('access-audit', function (User $user) {
            return in_array($user->role, ['coordinator', 'staff', 'admin'], true);
        });
    }
}
