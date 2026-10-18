<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Clase base de las pruebas de integración.
 *
 * OJO, y esto importa: estas pruebas NO se pudieron ejecutar. En el entorno donde se
 * construyó el portal no había PHP, ni Composer, ni PostgreSQL. El código está escrito
 * y es lo que se seguiría, pero nadie lo ha corrido todavía.
 *
 * Ver docs/10-PRUEBAS-Y-ESTADO.md, donde está separado lo verificado de lo pendiente.
 */
abstract class TestCase extends BaseTestCase
{
    //
}