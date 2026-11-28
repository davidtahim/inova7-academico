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

        Gate::define('manage-users', fn (User $user) => $user->canManageUsers());
        Gate::define('access-catalog', fn (User $user) => $user->canAccessCatalog());
        Gate::define('manage-catalog', fn (User $user) => $user->canManageCatalog());
        Gate::define('manage-planning', fn (User $user) => $user->canManagePlanning());
        Gate::define('access-imports', fn (User $user) => $user->canAccessImports());
        Gate::define('access-audit', fn (User $user) => $user->canAccessAudit());
        Gate::define('teach', fn (User $user) => $user->isTeacher());
        Gate::define('impersonate', fn (User $user, User $target) => ! session()->has('impersonator_id') && $user->canImpersonate($target));
        // Laboratórios: gestores de cada área (e o Administrador de TI) aprovam e cadastram; demais perfis (exceto aluno) consultam e pedem.
        Gate::define('view-labs', fn (User $user) => ! $user->isStudent());
        // Gestor de laboratórios (por área) e Administrador de TI; a área é conferida em cada ação.
        Gate::define('manage-labs', fn (User $user) => $user->isAdminTi() || ($user->isLabManager() && $user->lab_area));
    }
}
