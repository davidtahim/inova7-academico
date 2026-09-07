<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\Professor;
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
