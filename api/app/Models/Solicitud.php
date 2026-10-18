<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoSolicitud;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitud de onboarding.
 *
 * Aquí está la frontera de aislamiento. El modelo declara un alcance global que exige
 * empresa_id, de manera que una consulta sin filtro explícito no devuelve nada en vez
 * de devolverlo todo. El personal de Hefesto lo salta a propósito, mediante
 * sinAlcanceEmpresa(), que es una llamada visible en el código.
 *
 * @property int $id
 * @property int $empresa_id
 * @property EstadoSolicitud $estado
 * @property \Illuminate\Support\Carbon|null $aprobada_en
 * @property int|null $aprobada_por
 * @property string|null $coolify_proyecto_uuid
 * @property string|null $coolify_servicio_uuid
 */
class Solicitud extends Model
{
    /** @use HasFactory<\Database\Factories\SolicitudFactory> */
    use HasFactory;

    protected $table = 'solicitudes';

    protected $fillable = [
        'empresa_id',
        'plan_id',
        'creada_por',
        'estado',
        'resumen',
        'datos',
        'aprobada_por',
        'aprobada_en',
        'motivo_rechazo',
        'coolify_proyecto_uuid',
        'coolify_servicio_uuid',
        'coolify_entorno_uuid',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoSolicitud::class,
            'datos' => 'array',
            'aprobada_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('empresa', function (Builder $consulta): void {
            if (app()->bound('empresa.actual') && ($id = app('empresa.actual')) !== null) {
                $consulta->where('empresa_id', $id);
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(PasoAprovisionamiento::class)->orderBy('orden');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaCredencial::class);
    }

    public function aprobaciones(): HasMany
    {
        return $this->hasMany(Aprobacion::class);
    }

    /**
     * ¿Existe una aprobación humana que autorice ejecutar?
     *
     * No basta con que el estado sea "aprobada": se comprueba que hay una aprobación
     * registrada, con persona y fecha. Es la comprobación que hace el trabajo encolado
     * justo antes de llamar a Coolify.
     */
    public function tieneAprobacionRegistrada(): bool
    {
        return $this->aprobada_en !== null
            && $this->aprobada_por !== null
            && $this->aprobaciones()
                ->where('decision', 'aprobada')
                ->exists();
    }

    /**
     * ¿Puede ejecutarse ahora mismo?
     */
    public function puedeEjecutarse(): bool
    {
        return $this->estado->permiteEjecutar() && $this->tieneAprobacionRegistrada();
    }

    /** Último paso registrado, para la pantalla de seguimiento. */
    public function pasoActual(): ?PasoAprovisionamiento
    {
        return $this->pasos()->where('estado', 'en_curso')->first()
            ?? $this->pasos()->orderByDesc('orden')->first();
    }
}