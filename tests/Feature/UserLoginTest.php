<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\Professor;
use App\Models\ProfessorAvailability;
use App\Models\ScheduleSlot;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::create([
            'name' => 'Maria da Silva',
            'email' => 'maria@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'maria@inova7.local',
            'password' => 'senha1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'name' => 'Usuário Inativo',
            'email' => 'inativo@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inativo@inova7.local',
            'password' => 'senha1234',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_view_the_profile_page(): void
    {
        $user = User::create([
            'name' => 'Maria Souza',
            'email' => 'maria.perfil@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/perfil');

        $response->assertOk();
        $response->assertSee('Perfil do usuário');
    }

    public function test_teacher_sidebar_links_expose_disciplines_and_availability(): void
    {
        $user = User::create([
            'name' => 'Professora Ana',
            'email' => 'ana.prof@inova7.local',
            'password' => 'senha1234',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/perfil/editar');

        $response->assertOk();
        $response->assertSee('Minhas disciplinas');
        $response->assertSee('Minha disponibilidade');
        $response->assertDontSee('Disciplinas</h5>');
        $response->assertDontSee('Disponibilidade</h5>');
    }

    public function test_admin_routes_follow_the_admin_planejamento_prefix(): void
    {
        $user = User::create([
            'name' => 'Administrador',
            'email' => 'admin.routes@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.courses.index'))
            ->assertOk();

        $this->assertSame('/admin/planejamento/cursos', route('admin.courses.index', [], false));
    }

    public function test_topbar_allows_selecting_current_academic_term(): void
    {
        $user = User::create([
            'name' => 'Usuário com semestre',
            'email' => 'semestre@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $termOne = AcademicTerm::create([
            'code' => '2026.1',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-06-30',
            'status' => 'closed',
        ]);

        $termTwo = AcademicTerm::create([
            'code' => '2026.2',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Semestre');
        $response->assertSee('2026.2');

        $this->actingAs($user)
            ->from('/')->post('/semestre/selecionar', ['academic_term_id' => $termOne->id])
            ->assertRedirect('/');

        $this->assertEquals($termOne->id, session('selected_academic_term_id'));
    }

    public function test_teacher_availability_form_uses_standard_morning_and_evening_slots(): void
    {
        $user = User::create([
            'name' => 'Professora Ana',
            'email' => 'ana.prof.slot@inova7.local',
            'password' => 'senha1234',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/perfil/editar');

        $response->assertOk();
        $response->assertSee('07:30 às 08:20');
        $response->assertSee('10:50 às 11:40');
        $response->assertSee('18:30 às 19:20');
        $response->assertSee('21:00 às 21:50');
    }

    public function test_teacher_sees_only_subjects_allocated_by_coordinator(): void
    {
        $user = User::create([
            'name' => 'Professora Ana',
            'email' => 'ana.prof.subjects@inova7.local',
            'password' => 'senha1234',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $term = AcademicTerm::create([
            'code' => '2026.2',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'MTR-001',
            'name' => 'Matriz 2026',
            'status' => 'Atual',
        ]);

        $allocated = Subject::create([
            'code' => 'ALG101',
            'name' => 'Algoritmos',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $unallocated = Subject::create([
            'code' => 'BD101',
            'name' => 'Banco de Dados',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $professor = Professor::create([
            'name' => 'Professora Ana',
            'email' => 'ana.prof.subjects@inova7.local',
            'qualification' => 'Doutora',
            'active' => true,
        ]);

        $offering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $allocated->id,
            'class_code' => 'ALG-01',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        TeachingAssignment::create([
            'class_offering_id' => $offering->id,
            'professor_id' => $professor->id,
            'weekly_hours' => 4,
            'status' => 'planned',
        ]);

        $response = $this->actingAs($user)->get('/perfil/editar');

        $response->assertOk();
        $response->assertSee('Algoritmos');
        $response->assertDontSee('Banco de Dados');
    }

    public function test_catalog_courses_show_matrix_subject_structure_and_syllabus(): void
    {
        $user = User::create([
            'name' => 'Coordenadora',
            'email' => 'coord.catalog@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $course = Course::create([
            'code' => 'ADS',
            'name' => 'Análise e Desenvolvimento de Sistemas',
            'degree' => 'Tecnólogo',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'GRA-MAT-2140-H',
            'name' => 'Matriz 56 - Análise e Desenvolvimento de Sistemas',
            'version' => '56',
            'status' => 'Atual',
        ]);

        $subject = Subject::create([
            'code' => 'PROG101',
            'name' => 'Programação I',
            'total_hours' => 80,
            'presential_hours' => 80,
            'syllabus' => 'Fundamentos de lógica, algoritmos e programação estruturada.',
        ]);

        $matrix->subjects()->attach($subject->id, ['period' => 1, 'sequence' => 1]);

        $response = $this->actingAs($user)->get('/cursos');

        $response->assertOk();
        $response->assertSee('Estrutura da disciplina');
        $response->assertSee('Programação I');
        $response->assertSee('Abrir ementa');
        $response->assertSee('Fundamentos de lógica, algoritmos e programação estruturada.');
    }

    public function test_teacher_profile_hides_manual_subject_and_availability_editor(): void
    {
        $user = User::create([
            'name' => 'Professora Ana',
            'email' => 'ana.prof.readonly@inova7.local',
            'password' => 'senha1234',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $term = AcademicTerm::create([
            'code' => '2026.2',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'MTR-001',
            'name' => 'Matriz 2026',
            'status' => 'Atual',
        ]);

        $subject = Subject::create([
            'code' => 'ALG101',
            'name' => 'Algoritmos',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $professor = Professor::create([
            'name' => 'Professora Ana',
            'email' => 'ana.prof.readonly@inova7.local',
            'qualification' => 'Doutora',
            'active' => true,
        ]);

        $offering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subject->id,
            'class_code' => 'ALG-01',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        TeachingAssignment::create([
            'class_offering_id' => $offering->id,
            'professor_id' => $professor->id,
            'weekly_hours' => 4,
            'status' => 'planned',
        ]);

        $response = $this->actingAs($user)->get('/perfil/editar');

        $response->assertOk();
        $response->assertSee('A disponibilidade é preenchida pela oferta Ubíqua');
        $response->assertDontSee('name="subjects[]"');
        $response->assertDontSee('+ Adicionar horário');
    }

    public function test_only_it_staff_can_access_ubiquitous_import(): void
    {
        $coordinator = User::create([
            'name' => 'Coordenador',
            'email' => 'coord@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $itStaff = User::create([
            'name' => 'Funcionário de TI',
            'email' => 'ti@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($coordinator)->get('/importacoes/oferta-ubiqua');
        $response->assertForbidden();

        $allowed = $this->actingAs($itStaff)->get('/importacoes/oferta-ubiqua');
        $allowed->assertOk();
    }

    public function test_user_can_import_ubiquitous_offering_sheet(): void
    {
        $user = User::create([
            'name' => 'Larissa Torres',
            'email' => 'larissa@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $filePath = storage_path('framework/testing/ubiqua.csv');
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $csv = "CURSO;MATRIZ;DISCIPLINA;CODIGO;PERIODO;TURMA;TURNO;MODALIDADE;PROFESSOR;DIA;HORARIO;VAGAS\nSI;GRA-MAT-0228-F;Banco de Dados;GSER133620;2;CSE0280102NMA;MANHA;HÍBRIDA;Larissa Torres Ferreira;SEGUNDA;09:10-10:50;40\n";
        file_put_contents($filePath, $csv);

        $response = $this->actingAs($user)->post('/importacoes/oferta-ubiqua', [
            'arquivo' => new \Illuminate\Http\UploadedFile($filePath, 'ubiqua.csv', 'text/csv', null, true),
            'academic_term_code' => '2026.2',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('class_offerings', ['class_code' => 'CSE0280102NMA']);
        $this->assertDatabaseHas('subjects', ['code' => 'GSER133620']);
    }

    public function test_user_can_import_ubiquitous_offering_sheet_with_explicit_course_code(): void
    {
        $user = User::create([
            'name' => 'Larissa Torres',
            'email' => 'larissa-code@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $filePath = storage_path('framework/testing/ubiqua-course-code.csv');
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $csv = "CURSO;CODIGO_CURSO;DISCIPLINA;CODIGO;PERIODO;TURMA;TURNO;MODALIDADE\nSistemas de Informação;SI;Banco de Dados;GSER133620;2;CSE0280102NMA;MANHA;HÍBRIDA\n";
        file_put_contents($filePath, $csv);

        $response = $this->actingAs($user)->post('/importacoes/oferta-ubiqua', [
            'arquivo' => new \Illuminate\Http\UploadedFile($filePath, 'ubiqua-course-code.csv', 'text/csv', null, true),
            'academic_term_code' => '2026.2',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', ['code' => 'SI', 'name' => 'Sistemas de Informação']);
        $this->assertDatabaseHas('class_offerings', ['class_code' => 'CSE0280102NMA']);
    }

    public function test_user_can_import_ubiquitous_sheet_with_repeated_rows_missing_class_code(): void
    {
        $user = User::create([
            'name' => 'Larissa Torres',
            'email' => 'larissa-repeat@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $filePath = storage_path('framework/testing/ubiqua-repeated-no-turma.csv');
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $csv = "CURSO;MATRIZ;DISCIPLINA;CODIGO;PERIODO;TURNO;MODALIDADE\nSistemas de Informação;GRA-MAT-0228-F;Banco de Dados;GSER133620;2;MANHA;HÍBRIDA\nSistemas de Informação;GRA-MAT-0228-F;Banco de Dados;GSER133620;2;MANHA;HÍBRIDA\n";
        file_put_contents($filePath, $csv);

        $response = $this->actingAs($user)->post('/importacoes/oferta-ubiqua', [
            'arquivo' => new \Illuminate\Http\UploadedFile($filePath, 'ubiqua-repeated-no-turma.csv', 'text/csv', null, true),
            'academic_term_code' => '2026.2',
        ]);

        $response->assertRedirect();

        $term = \App\Models\AcademicTerm::where('code', '2026.2')->firstOrFail();
        $this->assertGreaterThanOrEqual(2, \App\Models\ClassOffering::where('academic_term_id', $term->id)->count());
    }

    public function test_user_can_import_ubiquitous_offering_sheet_without_secretariat_metadata(): void
    {
        $user = User::create([
            'name' => 'Larissa Torres',
            'email' => 'larissa2@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $filePath = storage_path('framework/testing/ubiqua-maintainer.csv');
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $csv = "CURSO;PERIODO;DISCIPLINA;MATRIZ;H/A CLASSIS (PAGAMENTO)\nSI;2;Banco de Dados;GRA-MAT-0228-F;4\n";
        file_put_contents($filePath, $csv);

        $response = $this->actingAs($user)->post('/importacoes/oferta-ubiqua', [
            'arquivo' => new \Illuminate\Http\UploadedFile($filePath, 'ubiqua-maintainer.csv', 'text/csv', null, true),
            'academic_term_code' => '2026.2',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', ['name' => 'Banco de Dados']);
        $this->assertDatabaseHas('class_offerings', ['period' => 2]);
    }

    public function test_user_can_import_ubiquous_sheet_with_document_metadata_before_header(): void
    {
        $user = User::create([
            'name' => 'Larissa Torres',
            'email' => 'larissa3@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $filePath = storage_path('framework/testing/ubiqua-metadata.csv');
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $csv = "Matriz baseada no Fliug - DOCUMENTOS: Coordenação de Curso - Graduação -> Matrizes e Planos de Ensino - Graduação PRESENCIAL -> Unidades\nAtenção: ...\nData da Criação da Planilha: 16/06/2017\nCURSO;PERIODO;DISCIPLINA;MATRIZ;PROFESSOR\nSI;2;Banco de Dados;GRA-MAT-0228-F;Larissa Torres Ferreira\n";
        file_put_contents($filePath, $csv);

        $response = $this->actingAs($user)->post('/importacoes/oferta-ubiqua', [
            'arquivo' => new \Illuminate\Http\UploadedFile($filePath, 'ubiqua-metadata.csv', 'text/csv', null, true),
            'academic_term_code' => '2025.1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', ['name' => 'Banco de Dados']);
        $this->assertDatabaseHas('class_offerings', ['period' => 2]);
    }

    public function test_user_can_import_ubiquitous_sheet_with_nonstandard_column_names(): void
    {
        $user = User::create([
            'name' => 'Larissa Torres',
            'email' => 'larissa4@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $filePath = storage_path('framework/testing/ubiqua-variant.csv');
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $csv = "Planilha de oferta ubíqua\nCurso;Matriz;Nome da Disciplina;Código da Disciplina;Período;Turma;Turno;Modalidade;Professor\nSI;GRA-MAT-0228-F;Banco de Dados;GSER133620;2;CSE0280102NMA;MANHÃ;HÍBRIDA;Larissa Torres Ferreira\n";
        file_put_contents($filePath, $csv);

        $response = $this->actingAs($user)->post('/importacoes/oferta-ubiqua', [
            'arquivo' => new \Illuminate\Http\UploadedFile($filePath, 'ubiqua-variant.csv', 'text/csv', null, true),
            'academic_term_code' => '2026.2',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', ['code' => 'GSER133620']);
        $this->assertDatabaseHas('class_offerings', ['class_code' => 'CSE0280102NMA']);
    }

    public function test_ubiquitous_import_is_scoped_by_academic_term(): void
    {
        $user = User::create([
            'name' => 'Larissa Torres',
            'email' => 'larissa5@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $filePath = storage_path('framework/testing/ubiqua-cross-term.csv');
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $csv = "CURSO;MATRIZ;DISCIPLINA;CODIGO;PERIODO;TURMA;TURNO;MODALIDADE;PROFESSOR\nSI;GRA-MAT-0228-F;Banco de Dados;GSER133620;2;CSE0280102NMA;MANHA;HÍBRIDA;Larissa Torres Ferreira\n";
        file_put_contents($filePath, $csv);

        $first = $this->actingAs($user)->post('/importacoes/oferta-ubiqua', [
            'arquivo' => new \Illuminate\Http\UploadedFile($filePath, 'ubiqua-cross-term.csv', 'text/csv', null, true),
            'academic_term_code' => '2026.1',
        ]);
        $first->assertRedirect();

        $second = $this->actingAs($user)->post('/importacoes/oferta-ubiqua', [
            'arquivo' => new \Illuminate\Http\UploadedFile($filePath, 'ubiqua-cross-term.csv', 'text/csv', null, true),
            'academic_term_code' => '2026.2',
        ]);
        $second->assertRedirect();

        $this->assertDatabaseHas('class_offerings', ['academic_term_id' => AcademicTerm::where('code', '2026.1')->value('id'), 'class_code' => 'CSE0280102NMA']);
        $this->assertDatabaseHas('class_offerings', ['academic_term_id' => AcademicTerm::where('code', '2026.2')->value('id'), 'class_code' => 'CSE0280102NMA']);
    }

    public function test_import_page_shows_recent_load_history_by_academic_term(): void
    {
        $user = User::create([
            'name' => 'Larissa Torres',
            'email' => 'larissa6@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $term = AcademicTerm::create([
            'code' => '2026.2',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $subject = Subject::create([
            'code' => 'BDA-01',
            'name' => 'Banco de Dados',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'class_code' => 'BDA-01',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        $response = $this->actingAs($user)->get('/importacoes/oferta-ubiqua');

        $response->assertOk();
        $response->assertSee('Últimas cargas por semestre');
        $response->assertSee('2026.2');
    }

    public function test_allocation_prefers_professor_with_matching_subject_and_preferred_availability(): void
    {
        $term = AcademicTerm::create([
            'code' => '2026.3',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'MTR-900',
            'name' => 'Matriz 2026',
            'status' => 'Atual',
        ]);

        $subject = Subject::create([
            'code' => 'BDA-01',
            'name' => 'Banco de Dados',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $generalistProfessor = Professor::create([
            'name' => 'Professor Geralista',
            'email' => 'geralista@inova7.local',
            'qualification' => 'Mestre',
            'active' => true,
        ]);

        $specialistProfessor = Professor::create([
            'name' => 'Professor Especialista',
            'email' => 'especialista@inova7.local',
            'qualification' => 'Doutor',
            'active' => true,
        ]);

        $specialistProfessor->subjects()->sync([$subject->id]);

        $offering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subject->id,
            'class_code' => 'BDA-01',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        ScheduleSlot::create([
            'class_offering_id' => $offering->id,
            'professor_id' => null,
            'weekday' => 2,
            'starts_at' => '09:10:00',
            'ends_at' => '10:50:00',
            'room' => 'LI31',
            'block' => '4º ANDAR',
        ]);

        ProfessorAvailability::create([
            'academic_term_id' => $term->id,
            'professor_id' => $generalistProfessor->id,
            'weekday' => 2,
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'preference' => 'available',
            'notes' => 'Disponível em geral',
        ]);

        ProfessorAvailability::create([
            'academic_term_id' => $term->id,
            'professor_id' => $specialistProfessor->id,
            'weekday' => 2,
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'preference' => 'preferred',
            'notes' => 'Preferência para banco de dados',
        ]);

        $service = new \App\Services\ProfessorAllocationService();
        $service->allocateForTerm($term->id);

        $this->assertDatabaseHas('teaching_assignments', [
            'class_offering_id' => $offering->id,
            'professor_id' => $specialistProfessor->id,
        ]);
    }

    public function test_allocation_ignores_inactive_professors(): void
    {
        $term = AcademicTerm::create([
            'code' => '2026.5',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'MTR-500',
            'name' => 'Matriz 2026',
            'status' => 'Atual',
        ]);

        $subject = Subject::create([
            'code' => 'ADS-05',
            'name' => 'Arquitetura de Software',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $inactiveProfessor = Professor::create([
            'name' => 'Professor Inativo',
            'email' => 'inativo@inova7.local',
            'qualification' => 'Mestre',
            'active' => false,
        ]);

        $activeProfessor = Professor::create([
            'name' => 'Professor Ativo',
            'email' => 'ativo@inova7.local',
            'qualification' => 'Doutor',
            'active' => true,
        ]);

        $inactiveProfessor->subjects()->sync([$subject->id]);
        $activeProfessor->subjects()->sync([$subject->id]);

        $offering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subject->id,
            'class_code' => 'ADS-05',
            'period' => 3,
            'shift' => 'NOITE',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        ScheduleSlot::create([
            'class_offering_id' => $offering->id,
            'professor_id' => null,
            'weekday' => 3,
            'starts_at' => '19:10:00',
            'ends_at' => '20:50:00',
            'room' => 'LI22',
            'block' => '4º ANDAR',
        ]);

        ProfessorAvailability::create([
            'academic_term_id' => $term->id,
            'professor_id' => $inactiveProfessor->id,
            'weekday' => 3,
            'starts_at' => '19:00:00',
            'ends_at' => '21:00:00',
            'preference' => 'preferred',
            'notes' => 'Disponível, mas inativo',
        ]);

        ProfessorAvailability::create([
            'academic_term_id' => $term->id,
            'professor_id' => $activeProfessor->id,
            'weekday' => 3,
            'starts_at' => '19:00:00',
            'ends_at' => '21:00:00',
            'preference' => 'preferred',
            'notes' => 'Disponível e ativo',
        ]);

        $service = new \App\Services\ProfessorAllocationService();
        $service->allocateForTerm($term->id);

        $this->assertDatabaseHas('teaching_assignments', [
            'class_offering_id' => $offering->id,
            'professor_id' => $activeProfessor->id,
        ]);

        $this->assertDatabaseMissing('teaching_assignments', [
            'class_offering_id' => $offering->id,
            'professor_id' => $inactiveProfessor->id,
        ]);
    }

    public function test_planning_page_shows_suggested_professor_and_compatibility_status(): void
    {
        $term = AcademicTerm::create([
            'code' => '2026.4',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'MTR-400',
            'name' => 'Matriz 2026',
            'status' => 'Atual',
        ]);

        $subject = Subject::create([
            'code' => 'DBA-04',
            'name' => 'Banco de Dados',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $professor = Professor::create([
            'name' => 'Professor Sugerido',
            'email' => 'sugerido@inova7.local',
            'qualification' => 'Doutor',
            'active' => true,
        ]);

        $professor->subjects()->sync([$subject->id]);

        $offering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subject->id,
            'class_code' => 'DBA-04',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        ScheduleSlot::create([
            'class_offering_id' => $offering->id,
            'professor_id' => null,
            'weekday' => 1,
            'starts_at' => '09:10:00',
            'ends_at' => '10:50:00',
            'room' => 'LI31',
            'block' => '4º ANDAR',
        ]);

        ProfessorAvailability::create([
            'academic_term_id' => $term->id,
            'professor_id' => $professor->id,
            'weekday' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'preference' => 'preferred',
            'notes' => 'Compatível com banco de dados',
        ]);

        $coordinator = User::create([
            'name' => 'Coordenador',
            'email' => 'coord.planejamento@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($coordinator)->get('/planejamento?term=' . $term->id);

        $response->assertOk();
        $response->assertSee('Professor Sugerido');
        $response->assertSee('Compatível');
    }

    public function test_coordinator_can_confirm_or_override_suggested_professor_for_an_offering(): void
    {
        $term = AcademicTerm::create([
            'code' => '2026.8',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'MTR-800',
            'name' => 'Matriz 2026',
            'status' => 'Atual',
        ]);

        $subject = Subject::create([
            'code' => 'DBA-08',
            'name' => 'Banco de Dados',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $professor = Professor::create([
            'name' => 'Professor Confirmado',
            'email' => 'confirmado@inova7.local',
            'qualification' => 'Doutor',
            'active' => true,
        ]);

        $professor->subjects()->sync([$subject->id]);

        $offering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subject->id,
            'class_code' => 'DBA-08',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        ScheduleSlot::create([
            'class_offering_id' => $offering->id,
            'professor_id' => null,
            'weekday' => 1,
            'starts_at' => '09:10:00',
            'ends_at' => '10:50:00',
            'room' => 'LI31',
            'block' => '4º ANDAR',
        ]);

        ProfessorAvailability::create([
            'academic_term_id' => $term->id,
            'professor_id' => $professor->id,
            'weekday' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'preference' => 'preferred',
            'notes' => 'Disponível',
        ]);

        $coordinator = User::create([
            'name' => 'Coordenador',
            'email' => 'coord.confirmacao@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($coordinator)->post('/planejamento/alocar/' . $offering->id, [
            'professor_id' => $professor->id,
        ]);

        $response->assertRedirect(route('planning.index', ['term' => $term->id]));
        $this->assertDatabaseHas('teaching_assignments', [
            'class_offering_id' => $offering->id,
            'professor_id' => $professor->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('schedule_slots', [
            'class_offering_id' => $offering->id,
            'professor_id' => $professor->id,
        ]);
    }

    public function test_planning_page_exposes_dropdown_to_change_professor_for_each_offering(): void
    {
        $term = AcademicTerm::create([
            'code' => '2026.9',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'MTR-900',
            'name' => 'Matriz 2026',
            'status' => 'Atual',
        ]);

        $subject = Subject::create([
            'code' => 'DBA-09',
            'name' => 'Banco de Dados',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $professorA = Professor::create([
            'name' => 'Professor A',
            'email' => 'professor-a@inova7.local',
            'qualification' => 'Doutor',
            'active' => true,
        ]);

        $professorB = Professor::create([
            'name' => 'Professor B',
            'email' => 'professor-b@inova7.local',
            'qualification' => 'Mestre',
            'active' => true,
        ]);

        $offering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subject->id,
            'class_code' => 'DBA-09',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        ScheduleSlot::create([
            'class_offering_id' => $offering->id,
            'professor_id' => null,
            'weekday' => 1,
            'starts_at' => '09:10:00',
            'ends_at' => '10:50:00',
            'room' => 'LI31',
            'block' => '4º ANDAR',
        ]);

        ProfessorAvailability::create([
            'academic_term_id' => $term->id,
            'professor_id' => $professorA->id,
            'weekday' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'preference' => 'preferred',
            'notes' => 'Disponível',
        ]);

        ProfessorAvailability::create([
            'academic_term_id' => $term->id,
            'professor_id' => $professorB->id,
            'weekday' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
            'preference' => 'available',
            'notes' => 'Também disponível',
        ]);

        $coordinator = User::create([
            'name' => 'Coordenador',
            'email' => 'coord.dropdown@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($coordinator)->get('/planejamento?term=' . $term->id);

        $response->assertOk();
        $response->assertSee('professor_id');
        $response->assertSee('Professor A');
        $response->assertSee('Professor B');
    }

    public function test_coordinator_can_assign_professor_in_bulk_for_selected_shift(): void
    {
        $term = AcademicTerm::create([
            'code' => '2026.10',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-15',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'SI',
            'name' => 'Sistemas de Informação',
            'degree' => 'Bacharelado',
            'active' => true,
        ]);

        $otherCourse = Course::create([
            'code' => 'ADS',
            'name' => 'Análise e Desenvolvimento de Sistemas',
            'degree' => 'Tecnólogo',
            'active' => true,
        ]);

        $matrix = CurriculumMatrix::create([
            'course_id' => $course->id,
            'code' => 'MTR-1000',
            'name' => 'Matriz 2026',
            'status' => 'Atual',
        ]);

        $otherMatrix = CurriculumMatrix::create([
            'course_id' => $otherCourse->id,
            'code' => 'MTR-1001',
            'name' => 'Matriz 2026 ADS',
            'status' => 'Atual',
        ]);

        $professor = Professor::create([
            'name' => 'Professor em Lote',
            'email' => 'lote@inova7.local',
            'qualification' => 'Doutor',
            'active' => true,
        ]);

        $subjectA = Subject::create([
            'code' => 'DBA-10A',
            'name' => 'Banco de Dados A',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $subjectB = Subject::create([
            'code' => 'DBA-10B',
            'name' => 'Banco de Dados B',
            'total_hours' => 40,
            'presential_hours' => 40,
        ]);

        $firstOffering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subjectA->id,
            'class_code' => 'DBA-10A',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        $secondOffering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'curriculum_matrix_id' => $matrix->id,
            'subject_id' => $subjectB->id,
            'class_code' => 'DBA-10B',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        $otherCourseOffering = ClassOffering::create([
            'academic_term_id' => $term->id,
            'course_id' => $otherCourse->id,
            'curriculum_matrix_id' => $otherMatrix->id,
            'subject_id' => $subjectB->id,
            'class_code' => 'ADS-10',
            'period' => 2,
            'shift' => 'MANHÃ',
            'modality' => 'PRESENCIAL',
            'occurs' => true,
            'weekly_hours' => 4,
            'totvs_hours' => 4,
            'status' => 'planned',
        ]);

        foreach ([$firstOffering, $secondOffering, $otherCourseOffering] as $offering) {
            ScheduleSlot::create([
                'class_offering_id' => $offering->id,
                'professor_id' => null,
                'weekday' => 1,
                'starts_at' => '09:10:00',
                'ends_at' => '10:50:00',
                'room' => 'LI31',
                'block' => '4º ANDAR',
            ]);
        }

        $coordinator = User::create([
            'name' => 'Coordenador',
            'email' => 'coord.lote@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $response = $this->actingAs($coordinator)->post('/planejamento/alocar-lote', [
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'shift' => 'MANHÃ',
            'professor_id' => $professor->id,
        ]);

        $response->assertRedirect(route('planning.index', ['term' => $term->id]));
        $this->assertDatabaseHas('teaching_assignments', ['class_offering_id' => $firstOffering->id, 'professor_id' => $professor->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('teaching_assignments', ['class_offering_id' => $secondOffering->id, 'professor_id' => $professor->id, 'status' => 'confirmed']);
        $this->assertDatabaseMissing('teaching_assignments', ['class_offering_id' => $otherCourseOffering->id, 'professor_id' => $professor->id]);
    }

    public function test_admin_can_manage_core_catalog_crud_forms(): void
    {
        $admin = User::create([
            'name' => 'Administrador de Cadastros',
            'email' => 'admin.crud@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin/planejamento/cursos/novo')->assertOk();
        $this->actingAs($admin)->post('/admin/planejamento/cursos', [
            'code' => 'ADS',
            'name' => 'Análise e Desenvolvimento de Sistemas',
            'degree' => 'Tecnólogo',
            'active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('courses', ['code' => 'ADS']);

        $this->actingAs($admin)->get('/admin/planejamento/disciplinas/novo')->assertOk();
        $this->actingAs($admin)->post('/admin/planejamento/disciplinas', [
            'code' => 'PROG-01',
            'name' => 'Programação I',
            'total_hours' => 80,
            'presential_hours' => 80,
        ])->assertRedirect();

        $this->assertDatabaseHas('subjects', ['code' => 'PROG-01']);

        $this->actingAs($admin)->get('/admin/planejamento/professores/novo')->assertOk();
        $this->actingAs($admin)->post('/admin/planejamento/professores', [
            'name' => 'Professor CRUD',
            'email' => 'crud.prof@inova7.local',
            'qualification' => 'Mestre',
            'active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('professors', ['email' => 'crud.prof@inova7.local']);
    }

    public function test_admin_can_manage_academic_terms(): void
    {
        $admin = User::create([
            'name' => 'Administrador de Semestres',
            'email' => 'admin.semestres@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin/planejamento/semestres/novo')->assertOk();
        $this->actingAs($admin)->post('/admin/planejamento/semestres', [
            'code' => '2026.7',
            'starts_at' => '2026-09-01',
            'ends_at' => '2027-01-31',
            'status' => 'planning',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_terms', ['code' => '2026.7']);
    }

    public function test_catalog_can_filter_students_and_subjects_by_search(): void
    {
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin.catalog@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Maria Souza',
            'email' => 'maria.aluno@inova7.local',
            'password' => 'senha1234',
            'role' => 'student',
            'registration_number' => '2026001',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'José Pereira',
            'email' => 'jose.aluno@inova7.local',
            'password' => 'senha1234',
            'role' => 'student',
            'registration_number' => '2026002',
            'is_active' => true,
        ]);

        $subjectOne = \App\Models\Subject::create([
            'code' => 'BD01',
            'name' => 'Banco de Dados',
            'total_hours' => 80,
        ]);

        $subjectTwo = \App\Models\Subject::create([
            'code' => 'ALG01',
            'name' => 'Algoritmos',
            'total_hours' => 60,
        ]);

        $this->actingAs($admin)
            ->get('/disciplinas?q=Banco')
            ->assertOk()
            ->assertSee('Banco de Dados')
            ->assertDontSee('Algoritmos');

        $this->actingAs($admin)
            ->get('/alunos?q=Maria')
            ->assertOk()
            ->assertSee('Maria Souza')
            ->assertDontSee('José Pereira');
    }

    public function test_student_cannot_access_catalog_and_audit_routes(): void
    {
        $student = User::create([
            'name' => 'Aluno Restringido',
            'email' => 'aluno.restrito@inova7.local',
            'password' => 'senha1234',
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->get('/cursos')
            ->assertForbidden();

        $this->actingAs($student)
            ->get('/auditoria')
            ->assertForbidden();
    }

    public function test_student_detail_page_shows_academic_history(): void
    {
        $admin = User::create([
            'name' => 'Administrador acadêmico',
            'email' => 'admin.historico@inova7.local',
            'password' => 'senha1234',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $student = User::create([
            'name' => 'Ana Student',
            'email' => 'ana.student@inova7.local',
            'password' => 'senha1234',
            'role' => 'student',
            'registration_number' => '2027001',
            'is_active' => true,
        ]);

        $term = AcademicTerm::create([
            'code' => '2027.1',
            'starts_at' => '2027-02-01',
            'ends_at' => '2027-06-30',
            'status' => 'active',
        ]);

        $course = Course::create([
            'code' => 'ADS',
            'name' => 'Análise e Desenvolvimento de Sistemas',
            'degree' => 'Tecnólogo',
            'active' => true,
        ]);

        $subject = Subject::create([
            'code' => 'PROG-01',
            'name' => 'Programação I',
            'total_hours' => 80,
            'presential_hours' => 80,
        ]);

        \App\Models\StudentAcademicRecord::create([
            'user_id' => $student->id,
            'academic_term_id' => $term->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'status' => 'aprovado',
            'grade' => 9.5,
            'credits' => 4,
            'observations' => 'Desempenho destacado',
        ]);

        $this->actingAs($admin)
            ->get('/alunos/' . $student->id)
            ->assertOk()
            ->assertSee('Histórico acadêmico')
            ->assertSee('Programação I')
            ->assertSee('Aprovado');
    }

    public function test_profile_page_shows_current_role_and_access_summary(): void
    {
        $user = User::create([
            'name' => 'Ana Costa',
            'email' => 'ana.perfil@inova7.local',
            'password' => 'senha1234',
            'role' => 'coordinator',
            'registration_number' => '2024102',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/perfil');

        $response->assertOk();
        $response->assertSee('Coordenador');
        $response->assertSee('Resumo de acesso');
        $response->assertSee('Perfil atual');
    }
}
