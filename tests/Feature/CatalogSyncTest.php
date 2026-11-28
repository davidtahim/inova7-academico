<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\Course;
use App\Models\CurriculumMatrix;
use App\Models\CurriculumMatrixDiscipline;
use App\Models\Subject;
use App\Services\CatalogSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sincronização do catálogo entre ambientes (banco local correto → produção).
 */
class CatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_becomes_equal_to_the_exported_catalog_without_losing_data(): void
    {
        // "Local" correto.
        $si = Course::create(['code' => 'GRA-PPC-0228-E', 'name' => 'Sistemas de Informação']);
        $m15 = CurriculumMatrix::create(['course_id' => $si->id, 'code' => 'GRA-MAT-0228-F', 'name' => 'Matriz 15', 'status' => 'Atual']);
        $lp = Subject::create(['code' => 'GSER895302', 'name' => 'Lógica de Programação', 'abbreviation' => 'LP']);
        $bd = Subject::create(['code' => 'GSER895400', 'name' => 'Banco de Dados']);
        CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $m15->id, 'subject_id' => $lp->id, 'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => 2, 'offering_type' => 'OBRIGATORIA']);
        CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $m15->id, 'subject_id' => $bd->id, 'period' => 2, 'shift' => 'NOITE', 'weekly_hours' => 3, 'offering_type' => 'OBRIGATORIA']);
        $file = app(CatalogSync::class)->export();

        // "Produção" desatualizada: nome e sigla diferentes, BD fora da grade, carga errada,
        // um vínculo a mais sem turma e outro a mais com turma; matriz nova inexistente.
        $lp->update(['name' => 'LOGICA PROG', 'abbreviation' => null]);
        CurriculumMatrixDiscipline::where('subject_id', $bd->id)->delete();
        CurriculumMatrixDiscipline::where('subject_id', $lp->id)->update(['weekly_hours' => 4]);
        $extra = Subject::create(['code' => 'X1', 'name' => 'Disciplina Sobrando']);
        CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $m15->id, 'subject_id' => $extra->id, 'period' => 3, 'shift' => 'NOITE', 'weekly_hours' => 2, 'offering_type' => 'OBRIGATORIA']);
        $used = Subject::create(['code' => 'X2', 'name' => 'Com Turma']);
        $usedLink = CurriculumMatrixDiscipline::create(['curriculum_matrix_id' => $m15->id, 'subject_id' => $used->id, 'period' => 4, 'shift' => 'NOITE', 'weekly_hours' => 2, 'offering_type' => 'OBRIGATORIA']);
        $term = AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        ClassOffering::create(['academic_term_id' => $term->id, 'course_id' => $si->id, 'curriculum_matrix_id' => $m15->id, 'curriculum_matrix_discipline_id' => $usedLink->id, 'subject_id' => $used->id, 'class_code' => 'T1', 'period' => 4, 'shift' => 'NOITE']);
        $file['matrices'][] = ['code' => 'GRA-MAT-2140-H', 'course_code' => 'GRA-PPC-0228-E', 'name' => 'Matriz 56', 'status' => 'Atual', 'disciplines' => [
            ['subject_code' => 'GSER895400', 'period' => 1, 'shift' => 'NOITE', 'weekly_hours' => 2, 'offering_type' => 'OBRIGATORIA'],
        ]];

        $report = app(CatalogSync::class)->apply($file);

        $this->assertSame('Lógica de Programação', $lp->fresh()->name);
        $this->assertSame('LP', $lp->fresh()->abbreviation);
        $grade = CurriculumMatrixDiscipline::with('subject')->where('curriculum_matrix_id', $m15->id)->orderBy('period')->get();
        $this->assertSame(['Lógica de Programação', 'Banco de Dados', 'Com Turma'], $grade->pluck('subject.name')->all());
        $this->assertSame(2, $grade[0]->weekly_hours);
        $this->assertSame(3, $grade[1]->weekly_hours);
        $this->assertSame(1, $report['removed'], 'Vínculo sem turma removido');
        $this->assertStringContainsString('Com Turma', $report['kept'][0]);
        $this->assertNotNull(Subject::where('code', 'X1')->first(), 'Disciplinas nunca são excluídas');
        $this->assertSame(1, CurriculumMatrix::where('code', 'GRA-MAT-2140-H')->firstOrFail()->disciplinesForOffering()->count());

        // Aplicar de novo não muda nada.
        $again = app(CatalogSync::class)->apply($file);
        $this->assertSame(0, $again['removed']);
    }

    public function test_command_writes_json_and_migration(): void
    {
        Course::create(['code' => 'C1', 'name' => 'Curso']);
        $before = glob(database_path('migrations/*_sincronizar_catalogo.php'));
        $jsonBefore = glob(database_path('data/catalogo/catalogo_*.json')) ?: [];

        $this->artisan('catalogo:exportar')->expectsOutputToContain('Migration criada')->assertSuccessful();

        $created = array_values(array_diff(glob(database_path('migrations/*_sincronizar_catalogo.php')), $before));
        $this->assertCount(1, $created);
        $json = array_values(array_diff(glob(database_path('data/catalogo/catalogo_*.json')), $jsonBefore));
        $this->assertCount(1, $json);
        $this->assertStringContainsString(basename($json[0]), file_get_contents($created[0]));

        // Limpa só o que este teste gerou (exportações reais ficam intactas).
        unlink($created[0]);
        unlink($json[0]);
        if ($jsonBefore === []) {
            @rmdir(database_path('data/catalogo'));
        }
    }
}
