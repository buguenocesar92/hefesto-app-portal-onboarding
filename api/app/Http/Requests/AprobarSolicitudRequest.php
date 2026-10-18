<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OperacionCoolify;
use App\Models\Usuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprobación de una solicitud.
 *
 * Además de las reglas de formato, comprueba que las operaciones que se piden autorizar
 * sean exactamente las que la acción va a ejecutar. Si alguien pide autorizar una
 * operación que no está en la lista acotada, la validación falla: el enum es la única
 * fuente de verdad sobre lo que este portal puede hacer.
 */
class AprobarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof Usuario && $usuario->puedeAprobar();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comentario' => ['nullable', 'string', 'max:1000'],
            'operaciones' => ['required', 'array', 'min:1'],
            'operaciones.*' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operaciones.required' => 'Confirma qué operaciones autorizas.',
            'operaciones.min' => 'Confirma qué operaciones autorizas.',
            'operaciones.*.string' => 'Cada operación autorizada debe existir en la lista del portal.',
        ];
    }

    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador): void {
            $permitidas = array_map(
                static fn (OperacionCoolify $operacion): string => $operacion->value,
                OperacionCoolify::permitidas(),
            );

            foreach ($this->input('operaciones', []) as $clave) {
                if (! in_array($clave, $permitidas, true)) {
                    $validador->errors()->add(
                        'operaciones',
                        sprintf('La operación "%s" no está en la lista permitida del portal.', (string) $clave),
                    );

                    return;
                }

                if (! OperacionCoolify::from($clave)->exigeAprobacionHumana()) {
                    $validador->errors()->add(
                        'operaciones',
                        sprintf('La operación "%s" es de solo lectura y no se autoriza.', (string) $clave),
                    );

                    return;
                }
            }
        });
    }

    public function usuario(): Usuario
    {
        /** @var Usuario $usuario */
        $usuario = $this->user();

        return $usuario;
    }
}