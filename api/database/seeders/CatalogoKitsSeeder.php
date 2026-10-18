<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Kit;
use Illuminate\Database\Seeder;

/**
 * Los catorce kits del catálogo de Hefesto.
 *
 * IMPORTANTE Y DELIBERADO: los catorce kits quedan con aprovisonable = false y
 * coolify_tipo en blanco.
 *
 * No es una omisión. Para que el portal cree un servicio, la API de Coolify necesita el
 * valor "type" del servicio de un solo clic, y ese valor no se pudo verificar contra la
 * instancia de Hefesto. La documentación de Coolify nombra algunos ejemplos
 * ("actualbudget", "calibre-web", "gitea-with-mysql"), y ninguno corresponde a un kit de
 * Hefesto. Poner un tipo inventado produciría un fallo en producción con un error poco
 * descriptivo, y además crearía un proyecto vacío en la plataforma de despliegue de un
 * cliente.
 *
 * Hasta verificar cada tipo contra la instancia, el portal ofrece el kit y dice que lo
 * prepara una persona de Hefesto. Ver docs/02-CATALOGO-Y-PLANES.md.
 *
 * Los precios NO están aquí: los mantiene Hefesto fuera del portal.
 */
class CatalogoKitsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->kits() as $kit) {
            Kit::updateOrCreate(
                ['codigo' => $kit['codigo']],
                $kit + [
                    'coolify_tipo' => null,
                    'coolify_imagen' => null,
                    'aprovisonable' => false,
                    'activo' => true,
                    'licencia' => $kit['licencia'] ?? null,
                    'requisitos' => $kit['requisitos'] ?? [],
                    'incluye' => $kit['incluye'] ?? [],
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kits(): array
    {
        return [
            [
                'codigo' => 'hefesto-facturacion-chile',
                'nombre' => 'Facturación Chile',
                'categoria' => 'Negocio',
                'resumen' => 'Emisión de documentos tributarios electrónicos en Chile.',
                'que_hace' => 'Prepara un servicio de facturación electrónica con los componentes '
                    .'necesarios para emitir documentos tributarios en Chile.',
                'licencia' => 'AGPL-3.0 (LibreDTE)',
                'requisitos' => ['Certificados digitales del cliente', 'Respaldo externo configurado'],
                'incluye' => ['Servicio de facturación', 'Documentación de puesta en marcha'],
                'orden' => 1,
            ],
            [
                'codigo' => 'hefesto-apps-internas',
                'nombre' => 'Apps Internas',
                'categoria' => 'Negocio',
                'resumen' => 'Paneles de administración y herramientas internas.',
                'que_hace' => 'Levanta un constructor de aplicaciones internas que el cliente puede '
                    .'usar para ordenar sus procesos y consultar sus propios datos.',
                'licencia' => 'Apache-2.0 (Appsmith Community)',
                'requisitos' => ['Acceso a los datos que se quieran mostrar'],
                'incluye' => ['Servicio de aplicaciones internas'],
                'orden' => 2,
            ],
            [
                'codigo' => 'hefesto-vps-administrado',
                'nombre' => 'VPS Administrado',
                'categoria' => 'Infraestructura',
                'resumen' => 'Panel de despliegue y operación en servidores del cliente.',
                'que_hace' => 'Prepara el panel desde el que Hefesto despliega y opera los sistemas '
                    .'del cliente.',
                'licencia' => 'Apache-2.0 (Coolify)',
                'requisitos' => ['Servidor con Ubuntu', 'Dominio apuntando al servidor'],
                'incluye' => ['Panel de despliegue', 'Procedimiento de operación'],
                'orden' => 3,
            ],
            [
                'codigo' => 'hefesto-ia-aplicada',
                'nombre' => 'IA Aplicada',
                'categoria' => 'Inteligencia artificial',
                'resumen' => 'Chatbots, búsqueda en documentos y agentes para PyMEs.',
                'que_hace' => 'Prepara una aplicación de inteligencia artificial lista para responder '
                    .'preguntas sobre los documentos del cliente.',
                'licencia' => 'Licencia Apache-2.0 modificada por el proyecto original',
                'requisitos' => ['Documentos para la base de conocimiento'],
                'incluye' => ['Aplicación de IA', 'Guía de carga de documentos'],
                'orden' => 4,
            ],
            [
                'codigo' => 'hefesto-observabilidad',
                'nombre' => 'Observabilidad',
                'categoria' => 'Operación',
                'resumen' => 'Métricas, registros y trazas de servidores y aplicaciones.',
                'que_hace' => 'Prepara la plataforma que permite ver cómo están los sistemas del '
                    .'cliente y detectar problemas antes de que se los note.',
                'licencia' => 'MIT en la aplicación; el colector tiene licencia copyleft',
                'requisitos' => ['Métricas de uso estimadas'],
                'incluye' => ['Plataforma de observabilidad'],
                'orden' => 5,
            ],
            [
                'codigo' => 'hefesto-respaldo-gestionado',
                'nombre' => 'Respaldo Gestionado',
                'categoria' => 'Operación',
                'resumen' => 'Copias de seguridad cifradas y deduplicadas, con prueba de restauración.',
                'que_hace' => 'Programa respaldos automáticos de los datos del cliente y permite '
                    .'comprobar que se pueden recuperar.',
                'licencia' => 'Apache-2.0',
                'requisitos' => ['Almacenamiento externo configurado'],
                'incluye' => ['Servicio de respaldos', 'Prueba de restauración'],
                'orden' => 6,
            ],
            [
                'codigo' => 'hefesto-antispam',
                'nombre' => 'Antispam',
                'categoria' => 'Correo',
                'resumen' => 'Filtrado de correo no deseado y firma de correo saliente.',
                'que_hace' => 'Reduce el correo basura que llega a la empresa y hace que el correo '
                    .'saliente no termine marcado como suplantación.',
                'licencia' => 'Apache-2.0',
                'requisitos' => ['Dominio con registros DNS configurados'],
                'incluye' => ['Filtrado antispam', 'Firma de correo saliente'],
                'orden' => 7,
            ],
            [
                'codigo' => 'hefesto-correo-corporativo',
                'nombre' => 'Correo Corporativo',
                'categoria' => 'Correo',
                'resumen' => 'Buzones de correo en el dominio del cliente, con webmail.',
                'que_hace' => 'Prepara el servicio de correo corporativo con buzones, acceso web y '
                    .'antispam.',
                'licencia' => 'MIT',
                'requisitos' => ['Dominio delegated', 'Puerto 25 saliente habilitado por el proveedor'],
                'incluye' => ['Buzones de correo', 'Acceso web'],
                'orden' => 8,
            ],
            [
                'codigo' => 'hefesto-base-datos-interna',
                'nombre' => 'Base de Datos Interna',
                'categoria' => 'Negocio',
                'resumen' => 'Base de datos tipo planilla, autoalojada, sin límite de filas.',
                'que_hace' => 'Entrega una base de datos con interfaz propia y API, para ordenar la '
                    .'información operativa de la empresa.',
                'licencia' => 'MIT en la edición abierta',
                'requisitos' => ['PostgreSQL con extensión pgvector si se usan funciones de similitud'],
                'incluye' => ['Base de datos', 'Interfaz de administración'],
                'orden' => 9,
            ],
            [
                'codigo' => 'hefesto-secretos',
                'nombre' => 'Secretos',
                'categoria' => 'Seguridad',
                'resumen' => 'Gestión de credenciales cifradas, en lugar de archivos sueltos.',
                'que_hace' => 'Guarda las contraseñas de los sistemas del cliente en un lugar cifrado '
                    .'y con registro de quién accede a qué.',
                'licencia' => 'MIT en el núcleo',
                'requisitos' => ['Respaldo de la clave de cifrado, guardada aparte'],
                'incluye' => ['Gestor de secretos'],
                'orden' => 10,
            ],
            [
                'codigo' => 'hefesto-observabilidad-ia',
                'nombre' => 'Observabilidad de IA',
                'categoria' => 'Inteligencia artificial',
                'resumen' => 'Trazas, costos y tiempos de respuesta de las aplicaciones de IA.',
                'que_hace' => 'Muestra cuánto cuesta cada respuesta de la IA y cuánto tarda, para que el '
                    .'cliente sepa qué está pagando.',
                'licencia' => 'MIT en el núcleo',
                'requisitos' => ['Aplicación de IA ya configurada'],
                'incluye' => ['Medición de la IA'],
                'orden' => 11,
            ],
            [
                'codigo' => 'hefesto-identidad',
                'nombre' => 'Identidad',
                'categoria' => 'Seguridad',
                'resumen' => 'Inicio de sesión único y segundo factor para todos los sistemas.',
                'que_hace' => 'Permite que el usuario entre a todos los sistemas del cliente con una '
                    .'sola cuenta y verificación en dos pasos.',
                'licencia' => 'MIT',
                'requisitos' => ['Acceso alternativo de emergencia documentado'],
                'incluye' => ['Inicio de sesión único', 'Verificación en dos pasos'],
                'orden' => 12,
            ],
            [
                'codigo' => 'hefesto-seguridad',
                'nombre' => 'Seguridad',
                'categoria' => 'Seguridad',
                'resumen' => 'Detección y bloqueo de ataques de fuerza bruta y escaneos.',
                'que_hace' => 'Detecta intentos de ataque contra los sistemas del cliente y bloquea la '
                    .'dirección que los realiza.',
                'licencia' => 'MIT',
                'requisitos' => ['Comprobación previa del acceso administrativo'],
                'incluye' => ['Detección de ataques', 'Bloqueo automático'],
                'orden' => 13,
            ],
            [
                'codigo' => 'hefesto-acceso-remoto',
                'nombre' => 'Acceso Remoto',
                'categoria' => 'Seguridad',
                'resumen' => 'Red privada para entrar a los servidores sin exponerlos a internet.',
                'que_hace' => 'Permite que Hefesto administre los servidores del cliente a través de una '
                    .'red privada, en vez de dejarlos abiertos.',
                'licencia' => 'BSD-3-Clause',
                'requisitos' => ['Versión fijada del cliente de red'],
                'incluye' => ['Red privada', 'Procedimiento de acceso'],
                'orden' => 14,
            ],
        ];
    }
}