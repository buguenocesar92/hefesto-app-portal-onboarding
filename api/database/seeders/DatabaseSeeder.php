<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orden de la siembra: primero el catálogo, después los datos de demostración, porque
 * el plan de ejemplo referencia kits del catálogo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogoKitsSeeder::class,
            DemoSeeder::class,
        ]);
    }
}