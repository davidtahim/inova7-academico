<?php

namespace Database\Seeders;

use App\Models\ClassOffering;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Carga horária e modalidade das disciplinas de SI e ADS, da "Planilha de Oferta Ubíqua"
 * (versão 24/06/2026, coluna 2026.2) — database/seeders/data/oferta-ubiqua-2026-2/si-ads.csv.
 *
 * Aulas por semana na grade = H/A Classis (para híbridas equivale à regra do RM:
 * 40h → 2, 60h → 2, 80h → 3). Modalidades a distância (DOL, NAVEGA, Notável Mestre) → 0.
 *
 *   php artisan db:seed --class=CargaHorariaSeeder --force
 */
class CargaHorariaSeeder extends Seeder
{
    private const FILE = __DIR__ . '/data/oferta-ubiqua-2026-2/si-ads.csv';

    public function run(): void
    {
        $rows = $this->rows();
        $updated = 0;
        $missing = [];

        foreach (CurriculumMatrix::whereIn('code', collect($rows)->pluck('matriz')->unique())->get() as $matrix) {
            $sheet = collect($rows)->where('matriz', $matrix->code);
            $links = CurriculumMatrixDiscipline::with('subject')->where('curriculum_matrix_id', $matrix->id)->get();

            foreach ($links as $link) {
                $row = $this->match($link->subject, $sheet);
                if (! $row) {
                    $missing[] = "{$matrix->name}: {$link->subject->name}";
                    continue;
                }

                $modality = self::modality($row['mod_2026_2']);
                $classes = in_array($modality, ClassOffering::DISTANCE_MODALITIES, true) ? 0 : (int) round((float) $row['ha_classis']);

                $link->update([
                    'total_hours' => (int) round((float) $row['ch_total']) ?: null,
                    'modality' => $modality,
                    'weekly_hours' => $classes,
                ]);
                $updated++;

                // Ofertas ainda sem carga própria herdam a da matriz.
                foreach (ClassOffering::where('curriculum_matrix_discipline_id', $link->id)->get() as $offering) {
                    $changes = [];
                    if ($offering->classes_per_week === null) {
                        $changes['classes_per_week'] = $classes;
                    }
                    if (in_array($modality, ClassOffering::DISTANCE_MODALITIES, true) && ! $offering->isDistance()) {
                        $changes['modality'] = $modality;
                    }
                    if (! $offering->directed_study && (float) $offering->weekly_hours === 2.0 && $classes > 0) {
                        $changes['weekly_hours'] = $classes; // substitui o placeholder de 2 aulas
                        $changes['totvs_hours'] = $classes;
                    }
                    if ($changes) {
                        $offering->update($changes);
                    }
                }
            }
        }

        $this->command?->info("Carga horária (Oferta Ubíqua 2026.2): {$updated} disciplinas de matriz atualizadas");
        if ($missing) {
            $this->command?->warn(count($missing) . ' disciplinas sem carga na planilha (ajuste na tela da matriz):');
            foreach ($missing as $line) {
                $this->command?->warn('  · ' . $line);
            }
        }
    }

    /** Nome igual; ou um contido no outro ("GESTÃO ÁGIL DE PROJETOS" ⊂ "GESTÃO DE TIMES … (GESTÃO ÁGIL DE PROJETOS)"). */
    private function match(Subject $subject, $sheet): ?array
    {
        $name = self::key($subject->name);

        return $sheet->first(fn ($row) => self::key($row['disciplina']) === $name)
            ?? $sheet->first(fn ($row) => strlen(self::key($row['disciplina'])) >= 8
                && (str_contains($name, self::key($row['disciplina'])) || str_contains(self::key($row['disciplina']), $name)));
    }

    public static function modality(string $value): string
    {
        $v = Str::upper(Str::ascii(trim($value)));

        return match (true) {
            str_starts_with($v, 'DOL') => 'DOL',
            str_starts_with($v, 'NAVEGA') => 'NAVEGA',
            str_contains($v, 'MESTRE') || str_starts_with($v, 'NTV') => 'NOTÁVEL MESTRE',
            str_starts_with($v, 'HIBRID') => 'HÍBRIDA',
            str_starts_with($v, 'EXTENS') => 'EXTENSÃO',
            str_starts_with($v, 'ESTAGIO') => 'ESTÁGIO',
            default => 'PRESENCIAL',
        };
    }

    private static function key(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($value))) ?? '';
    }

    private function rows(): array
    {
        $handle = fopen(self::FILE, 'r');
        $header = fgetcsv($handle, 0, ';');
        $rows = [];
        while (($values = fgetcsv($handle, 0, ';')) !== false) {
            $rows[] = array_combine($header, array_pad($values, count($header), ''));
        }
        fclose($handle);

        return $rows;
    }
}
