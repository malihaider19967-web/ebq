<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // SECURITY: the parent wires Horizon::auth() as
        //   Gate::check('viewHorizon') || app()->environment('local')
        // and this box runs APP_ENV=local IN PRODUCTION, so that `|| local` would
        // leave /horizon world-readable. Re-set the auth callback here (after
        // parent::boot) WITHOUT the local bypass — the viewHorizon gate (admins
        // only) is the sole authority, in every environment.
        Horizon::auth(fn ($request) => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Register the Horizon gate. The parent provider wires this into
     * Horizon::auth(), which REPLACES Horizon's default "allow everyone in the
     * local environment" check — critical here because this box runs
     * APP_ENV=local in production, so without an explicit gate /horizon would be
     * world-readable. Admins only, must be authenticated.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return (bool) ($user?->is_admin === true);
        });
    }
}
