<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\StudentSubjectStatus;
use App\Models\Subject;
use App\Models\User;
use App\Services\StudentHistoryPdfReader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Importação dos históricos em PDF direto na Enturmação (sem CSV). Dados fictícios.
 */
class StudentHistoryPdfImportTest extends TestCase
{
    use RefreshDatabase;

    private CurriculumMatrix $matrix;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $this->matrix = CurriculumMatrix::create(['course_id' => $course->id, 'code' => 'SI-2019.1', 'name' => 'Sistemas de Informação 2019.1', 'status' => 'Ativa']);
        $this->coordinator = User::create(['name' => 'Coord', 'email' => 'coord@inova7.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
    }

    private function historyHtml(string $registration, string $name): string
    {
        return <<<HTML
            <html><body style="font-family: DejaVu Sans; font-size: 9pt">
            <h3>HISTÓRICO ESCOLAR</h3>
            <table width="100%"><tr><td>MATRÍCULA</td><td>{$registration}</td><td>NOME</td><td>{$name}</td><td>CPF</td><td>000.000.000-00</td></tr></table>
            <table width="100%"><tr><td>CÓDIGO CURRÍCULO</td><td>CURSO</td><td>TURNO</td></tr>
            <tr><td>GSI2019.1</td><td>SISTEMAS DE INFORMAÇÃO</td><td>NOITE</td></tr></table>
            <h4>DISCIPLINAS CURSADAS / DISPENSADAS</h4>
            <table width="100%">
            <tr><td>20191</td><td>UNI7T001</td><td>ALGORITMOS I</td><td>80</td><td>8,50</td><td>APROVADO POR MÉDIA</td></tr>
            <tr><td>20192</td><td>GSER104163</td><td>DESENVOLVIMENTO PESSOAL E TRABALHABILIDADE</td><td>40</td><td></td><td>DISPENSADO</td></tr>
            </table>
            <h4>DISCIPLINAS CURSANDO / REPROVADAS</h4>
            <table width="100%">
            <tr><td>20242</td><td>UNI7T013</td><td>ENGENHARIA DE SOFTWARE</td><td>80</td><td>REPROVADO POR NOTA</td></tr>
            </table>
            <h4>FALTA CURSAR</h4>
            <table width="100%"><tr><td>8</td><td>UNI7T037</td><td>TRABALHO DE CONCLUSÃO DE CURSO II</td><td>80</td></tr></table>
            <h4>RESUMO GERAL</h4>
            </body></html>
            HTML;
    }

    private function pdf(string $html, string $filename = 'historico.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hist') . '.pdf';
        file_put_contents($path, Pdf::loadHTML($html)->output());

        return new UploadedFile($path, $filename, 'application/pdf', null, true);
    }

    public function test_reader_extracts_columns_from_the_pdf(): void
    {
        $file = $this->pdf($this->historyHtml('90000001', 'ALUNA FICTICIA UM'));
        $reader = app(StudentHistoryPdfReader::class);
        [$history] = $reader->parseAll($reader->text($file->getRealPath()));

        $this->assertSame(['matricula' => '90000001', 'nome' => 'ALUNA FICTICIA UM', 'curriculo' => 'GSI2019.1', 'turno' => 'NOITE'], $history['student']);
        $this->assertSame(
            ['UNI7T001' => 'aprovada', 'GSER104163' => 'dispensada', 'UNI7T013' => 'reprovada', 'UNI7T037' => 'falta_cursar'],
            collect($history['rows'])->pluck('situacao', 'codigo')->all()
        );
        $this->assertSame('ALGORITMOS I', $history['rows'][0]['disciplina']);
        $this->assertSame('8', $history['rows'][3]['periodo_matriz']);
        // Nada de CPF nas linhas importadas.
        $this->assertStringNotContainsString('000.000.000-00', json_encode($history['rows']));
    }

    public function test_reader_accepts_pdftotext_layout_with_several_students(): void
    {
        $text = "MATRÍCULA        90000001           NOME    ALUNA FICTICIA UM            CPF   000\n"
            . "CÓDIGO CURRÍCULO                          CURSO                       TURNO\n"
            . "GSI2019.1                                 SISTEMAS DE INFORMAÇÃO      NOITE\n"
            . "DISCIPLINAS CURSADAS / DISPENSADAS\n"
            . "20191      UNI7T001     ALGORITMOS I                                  80 8,50      APROVADO\n"
            . "MATRÍCULA        90000002           NOME    ALUNO FICTICIO DOIS\n"
            . "CÓDIGO CURRÍCULO                          CURSO                       TURNO\n"
            . "GSI2019.1                                 SISTEMAS DE INFORMAÇÃO      MANHÃ\n"
            . "FALTA CURSAR\n"
            . "8   UNI7T037             TRABALHO DE CONCLUSÃO DE CURSO II                 80\n";

        $histories = app(StudentHistoryPdfReader::class)->parseAll($text);

        $this->assertSame(['90000001', '90000002'], array_map(fn ($h) => $h['student']['matricula'], $histories));
        $this->assertSame('MANHÃ', $histories[1]['student']['turno']);
        $this->assertSame('falta_cursar', $histories[1]['rows'][0]['situacao']);
    }

    public function test_coordinator_uploads_pdfs_and_students_are_ready_for_enrollment(): void
    {
        $this->actingAs($this->coordinator)
            ->get(route('enrollment.index'))
            ->assertOk()
            ->assertSee('Importar históricos escolares (PDF)')
            ->assertSee('Sistemas de Informação 2019.1');

        $this->actingAs($this->coordinator)
            ->post(route('enrollment.import-histories'), [
                'matrix' => $this->matrix->id,
                'historicos' => [
                    $this->pdf($this->historyHtml('90000001', 'ALUNA FICTICIA UM'), 'a.pdf'),
                    $this->pdf($this->historyHtml('90000002', 'ALUNO FICTICIO DOIS'), 'b.pdf'),
                    $this->pdf('<p>Declaração qualquer</p>', 'declaracao.pdf'),
                ],
            ])
            ->assertRedirect(route('enrollment.index', ['matrix' => $this->matrix->id]))
            ->assertSessionHas('success')
            ->assertSessionHas('import_failed', ['declaracao.pdf']);

        $student = User::where('registration_number', '90000001')->firstOrFail();
        $this->assertSame('student', $student->role);
        $this->assertSame('Aluna Ficticia Um', $student->name);
        $this->assertSame($this->matrix->id, $student->curriculum_matrix_id);
        $this->assertSame('NOITE', $student->shift);
        $this->assertSame(2, User::where('role', 'student')->count());

        $status = StudentSubjectStatus::where('user_id', $student->id)
            ->where('subject_id', Subject::where('code', 'UNI7T001')->value('id'))->firstOrFail();
        $this->assertSame('aprovada', $status->status);
        $this->assertEquals(8.5, $status->grade);

        $this->actingAs($this->coordinator)->get(route('enrollment.index', ['matrix' => $this->matrix->id]))
            ->assertOk()->assertSee('Aluna Ficticia Um');
    }

    public function test_unreadable_files_are_reported(): void
    {
        $this->actingAs($this->coordinator)
            ->post(route('enrollment.import-histories'), [
                'matrix' => $this->matrix->id,
                'historicos' => [$this->pdf('<p>Sem histórico</p>', 'vazio.pdf')],
            ])
            ->assertSessionHasErrors('historicos');

        $this->assertSame(0, User::where('role', 'student')->count());
    }

    public function test_teacher_cannot_import_histories(): void
    {
        $teacher = User::create(['name' => 'Prof', 'email' => 'p@prof.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)
            ->post(route('enrollment.import-histories'), ['matrix' => $this->matrix->id])
            ->assertForbidden();
    }
}
