<?php

namespace Tests\Feature;

use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Importação da oferta semestral (2027.1) com as colunas oficiais da planilha:
 * Código da Turma; Cód Disciplina; Nome Disciplina; Curso; Matriz; Turno;
 * Hora Aula Semanal; Quantidade de Alunos.
 */
class OfferingImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "Código da Turma;Cód Disciplina;Nome Disciplina;Curso;Matriz;Turno;Hora Aula Semanal;Quantidade de Alunos";

    private User $coordinator;

    private CurriculumMatrix $si;

    private CurriculumMatrix $ads;

    private Subject $algoritmos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coordinator = User::create([
            'name' => 'Coordenação',
            'email' => 'coord@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $siCourse = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $adsCourse = Course::create(['code' => 'GRA-PPC-0240-G', 'name' => 'Análise e Desenvolvimento de Sistemas']);
        $this->si = CurriculumMatrix::create(['course_id' => $siCourse->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $this->ads = CurriculumMatrix::create(['course_id' => $adsCourse->id, 'code' => 'GRA-MAT-2140-H', 'name' => 'Matriz 56', 'status' => 'Atual']);

        $this->algoritmos = Subject::create(['code' => 'SI101', 'name' => 'Algoritmos', 'presential_hours' => 60]);
        CurriculumMatrixDiscipline::create([
            'curriculum_matrix_id' => $this->si->id,
            'subject_id' => $this->algoritmos->id,
            'period' => 1,
            'shift' => 'NOITE',
            'weekly_hours' => 3,
            'offering_type' => 'OBRIGATORIA',
            'required_room_type' => 'LABORATORIO_INFORMATICA',
            'allows_shared_class' => true,
        ]);
    }

    private function import(string $csvBody, string $term = '2027.1'): TestResponse
    {
        // Arquivo temporário do sistema, apagado ao final (não fica no repositório).
        $path = tempnam(sys_get_temp_dir(), 'oferta-') . '.csv';
        file_put_contents($path, self::HEADER . "\n" . $csvBody);

        try {
            return $this->actingAs($this->coordinator)->post(route('imports.ubiqua.store'), [
                'arquivo' => new UploadedFile($path, 'oferta.csv', 'text/csv', null, true),
                'academic_term_code' => $term,
            ]);
        } finally {
            @unlink($path);
            @unlink(substr($path, 0, -4));
        }
    }

    public function test_coordinator_imports_official_columns_and_links_matrix_discipline(): void
    {
        $this->import("SI0101N;SI101;Algoritmos;Sistemas de Informação;GRA-MAT-0228-F;Noite;3;45\n")
            ->assertRedirect()
            ->assertSessionHas('warning', fn ($message) => str_contains($message, '1 ofertas processadas (1 novas, 0 atualizadas)'));

        $offering = ClassOffering::where('class_code', 'SI0101N')->firstOrFail();
        $link = CurriculumMatrixDiscipline::firstOrFail();

        $this->assertSame('2027.1', $offering->term->code);
        $this->assertSame($this->si->id, $offering->curriculum_matrix_id);
        $this->assertSame($link->id, $offering->curriculum_matrix_discipline_id);
        $this->assertSame('NOITE', $offering->shift);
        $this->assertSame(1, (int) $offering->period); // período herdado do vínculo na matriz
        $this->assertEquals(3, $offering->weekly_hours);
        $this->assertSame(45, (int) $offering->student_count);
        $this->assertSame('LABORATORIO_INFORMATICA', $offering->matrixDiscipline->required_room_type);
    }

    public function test_matrix_can_be_referenced_by_number_or_name(): void
    {
        $this->import(
            "SI0101N;SI101;Algoritmos;Sistemas de Informação;15;NOITE;3;40\n" .
            "SI0101M;SI101;Algoritmos;Sistemas de Informação;Matriz 15;MANHÃ;3;30\n"
        )->assertRedirect();

        $this->assertSame(2, ClassOffering::where('curriculum_matrix_id', $this->si->id)->count());
        $this->assertSame(2, CurriculumMatrix::count(), 'Nenhuma matriz nova deve ser criada.');
    }

    public function test_integral_and_alternative_shift_names_are_recognized(): void
    {
        $this->import(
            "ADS01I;ADS10;Projeto Integrador;Análise e Desenvolvimento de Sistemas;GRA-MAT-2140-H;Integral;4;30\n" .
            "ADS01V;ADS11;Redes;Análise e Desenvolvimento de Sistemas;GRA-MAT-2140-H;Vespertino;2;30\n" .
            "ADS01N;ADS12;Web;Análise e Desenvolvimento de Sistemas;GRA-MAT-2140-H;Noturno;2;30\n"
        )->assertRedirect();

        $this->assertSame('INTEGRAL', ClassOffering::where('class_code', 'ADS01I')->value('shift'));
        $this->assertSame('TARDE', ClassOffering::where('class_code', 'ADS01V')->value('shift'));
        $this->assertSame('NOITE', ClassOffering::where('class_code', 'ADS01N')->value('shift'));
    }

    public function test_summary_reports_missing_matrix_links_and_unknown_shifts(): void
    {
        $this->import("ADS01X;ADS99;Tópicos;Análise e Desenvolvimento de Sistemas;GRA-MAT-2140-H;Madrugada;2;20\n")
            ->assertSessionHas('warning', function ($message) {
                return str_contains($message, '1 disciplina(s) sem vínculo na matriz curricular (ADS99)')
                    && str_contains($message, 'Turno(s) não reconhecido(s): MADRUGADA');
            });

        $this->assertSame('MANHÃ', ClassOffering::where('class_code', 'ADS01X')->value('shift'));
    }

    public function test_reimport_updates_instead_of_duplicating(): void
    {
        $this->import("SI0101N;SI101;Algoritmos;Sistemas de Informação;GRA-MAT-0228-F;Noite;3;45\n");
        $this->import("SI0101N;SI101;Algoritmos;Sistemas de Informação;GRA-MAT-0228-F;Noite;3;52\n")
            ->assertSessionHas('warning', fn ($message) => str_contains($message, '(0 novas, 1 atualizadas)'));

        $this->assertSame(1, ClassOffering::count());
        $this->assertSame(52, (int) ClassOffering::first()->student_count);
    }

    public function test_teacher_cannot_import(): void
    {
        $teacher = User::create([
            'name' => 'Prof',
            'email' => 'prof@inova7.local',
            'password' => 'senha1234',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->post(route('imports.ubiqua.store'), ['academic_term_code' => '2027.1'])
            ->assertForbidden();
    }
}
