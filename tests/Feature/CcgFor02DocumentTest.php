<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CCG-FOR-02 exportado pelo professor no modelo oficial (versão 06). */
class CcgFor02DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_exports_the_official_form_with_marks_and_subjects(): void
    {
        $term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $course = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação', 'coordinator_name' => 'Prof. Maria Souza']);
        $professor = Professor::create(['name' => 'Ana Exemplo', 'active' => true]);
        $professor->courses()->attach($course->id);
        $professor->subjects()->attach(Subject::create(['code' => 'UNI7T029', 'name' => 'SISTEMAS DE APOIO À DECISÃO'])->id);
        foreach (['18:30' => '19:20', '19:20' => '20:10'] as $start => $end) {
            ProfessorAvailability::create(['professor_id' => $professor->id, 'academic_term_id' => $term->id, 'weekday' => 1, 'starts_at' => $start, 'ends_at' => $end, 'preference' => 'available']);
        }
        $teacher = User::create(['name' => 'Ana Exemplo', 'email' => 'ana@prof.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true, 'professor_id' => $professor->id]);

        $response = $this->actingAs($teacher)->get(route('teacher.availability.pdf'))->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('CCG-FOR-02 - DISPONIBILIDADE DE HORARIO - ANA EXEMPLO - 2027.1.pdf', $response->headers->get('content-disposition'));

        // O HTML que vira PDF: cabeçalho oficial, coordenação, marcas e disciplinas.
        $html = $this->renderForm($professor, $term);
        $this->assertStringContainsString('DISPONIBILIDADE DE HORÁRIO DE PROFESSOR', $html);
        $this->assertStringContainsString('SISTEMAS DE INFORMAÇÃO – PROF. MARIA SOUZA', $html);
        $this->assertStringContainsString('18h30 às 19h20', $html);
        $this->assertSame(2, substr_count($html, '<td class="mark">x</td>'));
        $this->assertStringContainsString('Sistemas de Apoio à Decisão', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('Gerado pelo', $html);
    }

    public function test_coordinator_name_is_saved_on_the_course(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@x.local', 'password' => 'senha1234', 'role' => 'admin', 'is_active' => true]);
        $course = Course::create(['code' => 'C1', 'name' => 'Sistemas de Informação']);

        $this->actingAs($admin)->get(route('admin.courses.edit', $course))->assertOk()->assertSee('Coordenador(a) do curso');
        $this->actingAs($admin)->put(route('admin.courses.update', $course), ['code' => 'C1', 'name' => 'Sistemas de Informação', 'coordinator_name' => 'Prof. David', 'active' => 1])->assertRedirect();
        $this->assertSame('Prof. David', $course->fresh()->coordinator_name);
    }

    private function renderForm(Professor $professor, AcademicTerm $term): string
    {
        $controller = app(\App\Http\Controllers\UserController::class);
        $method = new \ReflectionMethod($controller, 'ccgFor02Data');

        return view('users.ccg-for-02-document', $method->invoke($controller, $professor, $term, 'x'))->render();
    }
}
