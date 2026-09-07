<?php

namespace Tests\Feature;

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
