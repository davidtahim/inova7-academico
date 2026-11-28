<?php

use App\Services\CatalogSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Deixa cursos, matrizes, disciplinas e grades iguais ao catálogo exportado em
 * database/data/catalogo/catalogo_2026_11_28_033528.json (gerado por "php artisan catalogo:exportar").
 * Não exclui disciplinas nem matrizes; ver App\Services\CatalogSync.
 */
return new class extends Migration
{
    public function up(): void
    {
        $report = app(CatalogSync::class)->applyFile(database_path('data/catalogo/catalogo_2026_11_28_033528.json'));
        logger()->info('Catálogo sincronizado (catalogo_2026_11_28_033528.json)', $report);
    }

    public function down(): void
    {
        // Sincronização de dados: não há como desfazer automaticamente.
    }
};
