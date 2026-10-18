<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una solicitud de onboarding.
 *
 * Dos comprobaciones que no son solo de formato:
 *
 *  - El plan tiene que existir y estar disponible. Si alguien manda un identificador
 *    inventado, la validación falla antes de tocar la base.
 *  - El plan tiene que traer al menos un kit que el portal sepa crear. Un plan entero
 *    compuesto de kits sin automatizar no es una solicitud: es un trabajo manual, y se
 *    hace por otro canal.
 */
class CrearSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->usuario() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists('planes', 'id')],
            'resumen' => ['nullable', 'string', 'min:10', 'max:2000'],
            'dominio' => ['nullable', 'string', 'max:253', 'regex:/^[a-z0-9.-]+$/i'],
            'contacto_tecnico' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Elige un plan para continuar.',
            'plan_id.exists' => 'Ese plan no existe.',
            'dominio.regex' => 'El dominio no tiene un formato válido.',
            'resumen.min' => 'Cuéntanos un poco más para poder preparar todo.',
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            $plan = Plan::find($this->input('plan_id'));

            if ($plan === null) {
                return;
            }

            if (! $plan->estaDisponible()) {
                $validador->errors()->add('plan_id', 'Ese plan no está disponible por ahora.');

                return;
            }

            if ($plan->kitsDelPlan()->isEmpty()) {
                $validador->errors()->add('plan_id', 'Ese plan no trae ningún kit activo.');
            }
        });
    }

    public function usuario(): Usuario
    {
        /** @var Usuario $usuario */
        $usuario = $this->user();

        return $usuario;
    }

    public function planId(): int
    {
        return (int) $this->validated()['plan_id'];
    }

    /**
     * Datos que se guardan con la solicitud. Sin información de credenciales: si el
     * cliente pone una contraseña en este formulario, no se guarda en esta base.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return array_filter([
            'dominio' => $this->validated()['dominio'] ?? null,
            'contacto_tecnico' => $this->validated()['contacto_tecnico'] ?? null,
        ], static fn ($valor): bool => $valor !== null);
    }

    public function resumen(): ?string
    {
        return $this->validated()['resumen'] ?? null;
    }
}