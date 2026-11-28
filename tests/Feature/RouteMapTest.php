<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Professor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mapa de rotas por área: cada perfil enxerga exatamente as áreas das suas funções,
 * e os endereços antigos continuam funcionando por redirecionamento.
 */
class RouteMapTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?Professor $professor = null): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role . '@x.local', 'password' => 'senha1234',
            'role' => $role, 'is_active' => true, 'professor_id' => $professor?->id,
        ]);
    }

    public function test_each_role_reaches_only_its_areas(): void
    {
        AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $professor = Professor::create(['name' => 'Ana', 'active' => true]);

        // rota => [admin, coordinator, staff, teacher, student]
        $matrix = [
            'schedules.index' => [true, true, true, true, true],
            'teacher.dashboard' => [false, false, false, true, false],
            'teacher.availability' => [false, false, false, true, false],
            'allocation.board' => [true, true, false, false, false],
            'allocation.index' => [true, true, false, false, false],
            'enrollment.index' => [true, true, false, false, false],
            'planning.index' => [true, true, false, false, false],
            'admin.planejamento.index' => [true, true, false, false, false],
            'admin.courses.index' => [true, true, false, false, false],
            'admin.professors.index' => [true, true, true, false, false],
            'catalog.courses' => [true, true, true, false, false],
            'imports.ubiqua.index' => [true, true, true, false, false],
            'admin.users.index' => [true, false, false, false, false],
            'audit.index' => [true, true, true, false, false],
        ];
        $roles = ['admin', 'coordinator', 'staff', 'teacher', 'student'];
        $users = [];
        foreach ($roles as $role) {
            $users[$role] = $this->user($role, $role === 'teacher' ? $professor : null);
        }

        foreach ($matrix as $route => $allowed) {
            foreach ($roles as $i => $role) {
                $status = $this->actingAs($users[$role])->get(route($route))->status();
                $this->assertSame($allowed[$i] ? 200 : 403, $status, "{$role} em {$route}");
            }
        }
    }

    public function test_home_sends_each_role_to_its_starting_point(): void
    {
        AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $professor = Professor::create(['name' => 'Ana', 'active' => true]);

        $this->actingAs($this->user('teacher', $professor))->get('/')->assertRedirect(route('teacher.dashboard'));
        $this->actingAs($this->user('student'))->get('/')->assertRedirect(route('schedules.index'));
        $this->actingAs($this->user('coordinator'))->get('/')->assertOk()->assertSee('Abrir grade global');
        $this->actingAs($this->user('staff'))->get('/')->assertOk()->assertDontSee('Abrir grade global');
    }

    public function test_legacy_addresses_redirect_permanently(): void
    {
        $admin = $this->user('admin');
        $redirects = [
            '/admin' => '/cadastros',
            '/admin/planejamento' => '/cadastros',
            '/admin/planejamento/cursos?q=ADS' => '/cadastros/cursos?q=ADS',
            '/admin/planejamento/professores/7/disponibilidade' => '/cadastros/professores/7/disponibilidade',
            '/admin/planejamento/usuarios/novo' => '/sistema/usuarios/novo',
            '/alocacao' => '/planejamento/montagem',
            '/alocacao/grade' => '/planejamento/grade',
            '/enturmacao' => '/planejamento/enturmacao',
            '/auditoria' => '/sistema/auditoria',
            '/cursos' => '/consultas/cursos',
            '/alunos/5' => '/consultas/alunos/5',
            '/perfil/disponibilidade/pdf' => '/docente/disponibilidade/pdf',
        ];
        foreach ($redirects as $from => $to) {
            $this->actingAs($admin)->get($from)->assertStatus(301)->assertRedirect($to);
        }
        $this->actingAs($admin)->get('/planejamento')->assertRedirect('/planejamento/grade');
    }

    public function test_forbidden_page_explains_and_keeps_navigation(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)->get(route('admin.courses.index'))
            ->assertForbidden()
            ->assertSee('Você não tem acesso a esta página')
            ->assertSee('não faz parte das funções do seu perfil')
            ->assertSee('Meus horários');

        $this->actingAs($student)->get('/nao-existe')->assertNotFound()->assertSee('Não encontramos esta página');
    }

    public function test_teacher_area_uses_own_record_and_returns_there_after_saving(): void
    {
        AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $ana = Professor::create(['name' => 'Ana', 'active' => true]);
        $bruno = Professor::create(['name' => 'Bruno', 'active' => true]);
        $teacher = $this->user('teacher', $ana);

        $this->actingAs($teacher)->get(route('teacher.subjects'))->assertOk()->assertViewHas('professor', fn ($p) => $p->is($ana));
        $this->actingAs($teacher)->put(route('admin.professors.update-subjects', $ana), ['subject_ids' => []])
            ->assertRedirect(route('teacher.subjects'));
        $this->actingAs($teacher)->get(route('admin.professors.subjects', $bruno))->assertForbidden();

        // Coordenação editando o mesmo professor volta para a área de cadastros.
        $this->actingAs($this->user('coordinator'))->put(route('admin.professors.update-subjects', $ana), ['subject_ids' => []])
            ->assertRedirect(route('admin.professors.subjects', $ana));

        // Professor sem vínculo recebe uma explicação, não um erro genérico.
        $unlinked = User::create(['name' => 'Sem vínculo', 'email' => 'sv@x.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);
        $this->actingAs($unlinked)->get(route('teacher.availability'))->assertForbidden()->assertSee('não está vinculado');
    }
}
