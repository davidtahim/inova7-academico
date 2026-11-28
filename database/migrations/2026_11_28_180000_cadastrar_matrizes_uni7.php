<?php

use App\Models\Course;
use App\Models\CurriculumMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cadastra os cursos e as matrizes Atual/Ativa da planilha "Matrizes" (TOTVS), exceto SI e ADS,
 * que já estão corretos no sistema. Dados em database/data/catalogo/matrizes_uni7.json.
 *
 * Nunca exclui nada. Curso: procura pelo código e depois pelo nome; se não houver, cria.
 * Matriz: procura pelo curso + código; se existir, atualiza só o status (e a versão vazia).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $data = json_decode((string) file_get_contents(database_path('data/catalogo/matrizes_uni7.json')), true);
        $key = fn (string $name) => Str::of(Str::ascii($name))->lower()->replaceMatches('/^(bacharelado|licenciatura|tecnologia) em /', '')->squish()->toString();

        DB::transaction(function () use ($data, $key) {
            $existing = Course::all();
            $courses = [];
            foreach ($data['courses'] as $item) {
                $course = $existing->firstWhere('code', $item['code'])
                    ?? $existing->first(fn ($c) => $key($c->name) === $key($item['name']));
                $courses[$item['code']] = $course ?? Course::create($item);
            }

            foreach ($data['matrices'] as $item) {
                $course = $courses[$item['course_code']];
                $matrix = CurriculumMatrix::where('course_id', $course->id)->where('code', $item['code'])->first();
                if ($matrix) {
                    $matrix->update(['status' => $item['status'], 'version' => $matrix->version ?: $item['version']]);
                    continue;
                }
                CurriculumMatrix::create([
                    'course_id' => $course->id,
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'version' => $item['version'],
                    'status' => $item['status'],
                    'effective_from' => $item['effective_from'],
                ]);
            }
        });
    }

    public function down(): void
    {
        // Cadastro de dados: não desfaz (cursos e matrizes podem ter recebido disciplinas e turmas).
    }
};
