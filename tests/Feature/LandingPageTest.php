<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Apresentação do sistema antes do login. */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_presentation_with_link_to_login(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('O semestre inteiro planejado num só lugar.')
            ->assertSee('Seis passos, do catálogo à sala de aula')
            ->assertSee('Cada pessoa vê só o que precisa')
            ->assertSee(route('login'), false)
            ->assertSee(route('labs.public'), false);
    }

    public function test_login_page_links_back_to_the_presentation(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Conheça o sistema')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('data-toggle-password', false);
    }

    public function test_logged_in_user_still_lands_on_the_profile_start_page(): void
    {
        $student = User::create(['name' => 'Aluno', 'email' => 'a@x.local', 'password' => 'senha1234', 'role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get('/')->assertRedirect(route('schedules.index'));
        $this->actingAs($student)->get(route('about'))->assertOk()->assertSee('Ir para o sistema');
    }

    public function test_protected_pages_still_send_guests_to_login(): void
    {
        $this->get('/horarios')->assertRedirect(route('login'));
    }

    public function test_teacher_tutorial_pdf_is_published_and_linked(): void
    {
        $this->assertFileExists(public_path('docs/tutorial-disponibilidade-professor.pdf'));

        $professor = \App\Models\Professor::create(['name' => 'Prof', 'active' => true]);
        $teacher = User::create(['name' => 'Prof', 'email' => 'p@prof.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true, 'professor_id' => $professor->id]);
        \App\Models\AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);

        $this->actingAs($teacher)->get(route('teacher.availability'))->assertOk()->assertSee('docs/tutorial-disponibilidade-professor.pdf', false)->assertSee('Tutorial (PDF)');
        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk()->assertSee('Baixe o tutorial (PDF)');
    }

    public function test_coordination_tutorial_pdf_is_published_and_linked(): void
    {
        $this->assertFileExists(public_path('docs/tutorial-coordenacao.pdf'));
        $coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);

        $this->actingAs($coordinator)->get('/')->assertOk()->assertSee('docs/tutorial-coordenacao.pdf', false);
    }
}
