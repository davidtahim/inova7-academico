<?php

namespace App\Console\Commands;

use App\Services\CatalogSync;
use Illuminate\Console\Command;

/**
 * Exporta o catálogo (cursos, matrizes, disciplinas e grades) deste banco e gera a
 * migration que o aplica em outro ambiente. Rode no banco que está correto.
 */
class ExportCatalog extends Command
{
    protected $signature = 'catalogo:exportar {--sem-migration : Só gera o arquivo JSON}';

    protected $description = 'Exporta matrizes e disciplinas para aplicar em outro ambiente (gera JSON + migration)';

    public function handle(CatalogSync $sync): int
    {
        $data = $sync->export();
        $stamp = now()->format('Y_m_d_His');
        $dir = database_path('data/catalogo');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $jsonName = "catalogo_{$stamp}.json";
        file_put_contents("{$dir}/{$jsonName}", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $links = array_sum(array_map(fn ($m) => count($m['disciplines']), $data['matrices']));
        $this->info("Catálogo exportado: database/data/catalogo/{$jsonName}");
        $this->line('  ' . count($data['courses']) . ' cursos, ' . count($data['matrices']) . ' matrizes, ' . count($data['subjects']) . " disciplinas, {$links} vínculos de grade.");

        if (! $this->option('sem-migration')) {
            $migration = database_path("migrations/{$stamp}_sincronizar_catalogo.php");
            file_put_contents($migration, <<<PHP
<?php

use App\\Services\\CatalogSync;
use Illuminate\\Database\\Migrations\\Migration;

/**
 * Deixa cursos, matrizes, disciplinas e grades iguais ao catálogo exportado em
 * database/data/catalogo/{$jsonName} (gerado por "php artisan catalogo:exportar").
 * Não exclui disciplinas nem matrizes; ver App\\Services\\CatalogSync.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dados reais: não entram no banco dos testes automatizados.
        if (app()->environment('testing')) {
            return;
        }
        \$report = app(CatalogSync::class)->applyFile(database_path('data/catalogo/{$jsonName}'));
        logger()->info('Catálogo sincronizado ({$jsonName})', \$report);
    }

    public function down(): void
    {
        // Sincronização de dados: não há como desfazer automaticamente.
    }
};

PHP);
            $this->info('Migration criada: database/migrations/' . basename($migration));
            $this->newLine();
            $this->line('Próximos passos: faça commit e push dos dois arquivos; na produção, "Atualizar do remoto"');
            $this->line('e depois Sistema › Manutenção › "Aplicar migrations pendentes".');
        }

        return self::SUCCESS;
    }
}
