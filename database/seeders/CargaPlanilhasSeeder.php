<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Services\ScheduleAllocator;
use Illuminate\Database\Seeder;

/**
 * Carga completa a partir das planilhas de oferta:
 *   1. 2027.1 (oferta a montar) — Planilha20271Seeder
 *   2. 2026.1 (oferta já montada, com professor/dia/horário/sala) — Planilha20261Seeder
 *   3. Grade 2027.1 sugerida a partir da 2026.1 pelo motor de alocação (sem choques)
 *
 *   php artisan migrate:fresh --seeder=CargaPlanilhasSeeder
 */
class CargaPlanilhasSeeder extends Seeder
{
    public function run(ScheduleAllocator $allocator): void
    {
        $this->call([Planilha20271Seeder::class, RoomsSeeder::class, Planilha20261Seeder::class]);

        $report = $allocator->copyFromTerm(
            AcademicTerm::where('code', '2026.1')->firstOrFail(),
            AcademicTerm::where('code', '2027.1')->firstOrFail()
        );

        $this->command?->info(sprintf(
            'Grade 2027.1 a partir da 2026.1: %d com horário copiado, %d só com professor (choque de horário), %d sem equivalente em 2026.1, %d EaD/DOL em 2026.1',
            $report['copied'], $report['professor_only'], $report['without_match'], $report['skipped_distance']
        ));
        foreach (array_slice($report['conflicts'], 0, 15) as $conflict) {
            $this->command?->warn('  · ' . $conflict);
        }
        if (count($report['conflicts']) > 15) {
            $this->command?->warn('  … e mais ' . (count($report['conflicts']) - 15) . ' choques (veja a tela de montagem de horários).');
        }
    }
}
