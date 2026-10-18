<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Rol;
use App\Models\Solicitud;
use App\Models\Usuario;
use App\Policies\SolicitudPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Arranque del portal.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Barrera global contra consultas que dejen columnas vacías o mezclen tenants.
        // Con esto se suenan en desarrollo y en las pruebas, no en producción.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::policy(Solicitud::class, SolicitudPolicy::class);

        Gate::define('es-hefesto', fn (Usuario $usuario): bool => $usuario->rol === Rol::Aprobador
            || $usuario->rol === Rol::Administrador);
    }
}