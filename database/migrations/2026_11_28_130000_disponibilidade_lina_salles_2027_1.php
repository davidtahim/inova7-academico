<?php

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lança o CCG-FOR-02 assinado por Lina Angela Oliveira Salles Moreira (2027.1, assinado em 26/10/2026):
 * - disponível segunda e quarta à noite, blocos A a D (18:30–22:00); os demais horários, indisponível;
 * - habilitada em Sistemas de Apoio à Decisão, Auditoria de Sistemas e APIEX (I a IV).
 *
 * A disponibilidade de 2027.1 dela é substituída pela do formulário; a habilitação só é acrescentada.
 * Sem o semestre ou a professora (ex.: banco de testes), não faz nada.
 */
return new class extends Migration
{
    private const NIGHT = [['18:30', '19:20'], ['19:20', '20:10'], ['20:20', '21:10'], ['21:10', '22:00']];

    private const WEEKDAYS = [1, 3]; // segunda e quarta

    private const SUBJECTS = ['UNI7T029', 'UNI7T006', 'GSER2000800', 'GSER2023400', 'GSER98975', 'GSER98976'];

    public function up(): void
    {
        $term = AcademicTerm::where('code', '2027.1')->first();
        $professor = Professor::where('email', '560127699@prof.uni7.edu.br')->first()
            ?? Professor::where('registration', 'UNI727699')->first()
            ?? Professor::where('name', 'like', 'Lina Angela%Salles%')->first();

        if (! $term || ! $professor) {
            return;
        }

        DB::transaction(function () use ($term, $professor) {
            ProfessorAvailability::where('professor_id', $professor->id)->where('academic_term_id', $term->id)->delete();
            foreach (self::WEEKDAYS as $weekday) {
                foreach (self::NIGHT as [$start, $end]) {
                    ProfessorAvailability::create([
                        'professor_id' => $professor->id,
                        'academic_term_id' => $term->id,
                        'weekday' => $weekday,
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'preference' => 'available',
                        'notes' => 'CCG-FOR-02 assinado em 26/10/2026',
                    ]);
                }
            }

            $professor->subjects()->syncWithoutDetaching(Subject::whereIn('code', self::SUBJECTS)->pluck('id')->all());
        });
    }

    public function down(): void
    {
        // Lançamento de dados: não há como saber o que havia antes.
    }
};
