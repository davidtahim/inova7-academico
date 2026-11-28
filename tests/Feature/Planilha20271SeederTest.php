<?php

namespace Tests\Feature;

use App\Models\ClassOffering;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Professor;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\Planilha20271Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carga da planilha "Disciplinas 2027.1" (database/seeders/data/planilha-2027-1).
 */
class Planilha20271SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_sheet_data(): void
    {
        $this->seed(Planilha20271Seeder::class);

        $this->assertSame(135, ClassOffering::count(), '74 SI + 40 ADS + 21 IA');
        $this->assertSame(43, TeachingAssignment::count());
        $this->assertSame(12, Professor::count());
        $this->assertSame(4, CurriculumMatrix::count());

        // Período vem do código da turma; matriz e vínculo vêm da coluna Matriz.
        $offering = ClassOffering::where('class_code', 'CSE0280107NNA')->whereHas('subject', fn ($q) => $q->where('code', 'GSER190500'))->firstOrFail();
        $this->assertSame(7, (int) $offering->period);
        $this->assertSame('GRA-MAT-0228-E', $offering->matrixDiscipline->curriculumMatrix->code);
        $this->assertSame('Evandro Carvalho de Andrade', $offering->assignments->first()->professor->name);

        // Currículo completo da Matriz 14 vem da aba "Matriz Grupo Ser E".
        $this->assertSame(55, CurriculumMatrixDiscipline::whereHas('curriculumMatrix', fn ($q) => $q->where('code', 'GRA-MAT-0228-E'))->count());

        // Mesma disciplina ofertada em SI e ADS → aula compartilhada.
        $this->assertTrue(CurriculumMatrixDiscipline::whereHas('subject', fn ($q) => $q->where('code', 'GSER133668'))->where('allows_shared_class', true)->exists());

        // "Larissa" (aba IA) casa com o cadastro completo; DOL/GOKURSO não viram professores.
        $this->assertFalse(Professor::whereIn('name', ['DOL', 'GOKURSO'])->exists());
        $larissa = Professor::where('name', 'Larissa Torres Ferreira')->firstOrFail();
        $this->assertTrue(TeachingAssignment::where('professor_id', $larissa->id)->whereHas('offering', fn ($q) => $q->where('class_code', 'CSE3300101NMA'))->exists());

        // Usuário docente vinculado, com troca de senha obrigatória.
        $david = User::where('email', '560127720@prof.uni7.edu.br')->firstOrFail();
        $this->assertSame('teacher', $david->role);
        $this->assertTrue($david->must_change_password);
        $this->assertSame('UNI727720', $david->professor->registration);
        $this->assertSame('560127720', $david->professor->chapa);

        $this->assertTrue(User::where('email', 'admin@inova7.local')->where('role', 'admin')->exists());
    }

    public function test_seeder_keeps_existing_users_passwords(): void
    {
        // Produção: admin e professor já existentes não podem ter a senha redefinida.
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@inova7.local', 'password' => 'SenhaForte#2027', 'role' => 'admin', 'is_active' => true]);
        $teacher = User::create(['name' => 'David', 'email' => '560127720@prof.uni7.edu.br', 'password' => 'OutraSenha#2027', 'role' => 'teacher', 'is_active' => true]);

        $this->seed(Planilha20271Seeder::class);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('SenhaForte#2027', $admin->fresh()->password));
        $this->assertFalse((bool) $admin->fresh()->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('OutraSenha#2027', $teacher->fresh()->password));
        $this->assertSame('UNI727720', $teacher->fresh()->professor->registration);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(Planilha20271Seeder::class);
        $this->seed(Planilha20271Seeder::class);

        $this->assertSame(135, ClassOffering::count());
        $this->assertSame(43, TeachingAssignment::count());
        $this->assertSame(12, Professor::count());
    }
}
