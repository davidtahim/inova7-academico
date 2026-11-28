<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseAudit;
use App\Models\CourseAuditDocument;
use App\Models\User;
use App\Services\CourseAuditCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Auditoria de curso (QUA-INT-08 v.25): checklist, documentos e resultado previsto. */
class CourseAuditTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        AcademicTerm::create(['code' => '2027.1', 'status' => 'planning']);
        $this->course = Course::create(['code' => 'SI', 'name' => 'Sistemas de Informação']);
        $this->coordinator = User::create(['name' => 'Coord', 'email' => 'c@x.local', 'password' => 'senha1234', 'role' => 'coordinator', 'is_active' => true]);
    }

    public function test_catalog_has_the_instrument(): void
    {
        $catalog = app(CourseAuditCatalog::class);
        $this->assertSame(54, $catalog->indicators()->count());
        $this->assertSame(2, $catalog->indicator('1.1')['weight']);
        $this->assertTrue($catalog->indicator('1.1')['essential']);
        $this->assertSame('simnao', $catalog->indicator('1.11.1')['kind']);
        $this->assertSame(2, $catalog->groups()['1.11']['weight']);
        $this->assertContains('CCG-FOR-01 - Horário de aula', $catalog->indicator('1.12.1')['documents']);
        $this->assertSame('Delegação II', $catalog->band(4.2));
        $this->assertSame('Gestão I', $catalog->band(3.0));
        $this->assertSame('Supervisão I', $catalog->band(1.2));
    }

    public function test_coordinator_scores_indicators_and_delivers_documents(): void
    {
        Storage::fake('local');
        $this->actingAs($this->coordinator)->get(route('audit.courses.index'))->assertOk()->assertSee('Sistemas de Informação');
        $this->actingAs($this->coordinator)->get(route('audit.courses.show', $this->course))->assertOk()
            ->assertSee('1.1')->assertSee('Projeto Pedagógico de Curso')->assertSee('CCG-FOR-01 - Horário de aula')->assertSee('Imprescindível');

        $key = CourseAuditDocument::keyFor('1.12.1', 'CCG-FOR-01 - Horário de aula');
        $this->actingAs($this->coordinator)->post(route('audit.courses.indicator', [$this->course, '1.12.1']), [
            'score' => 5,
            'notes' => 'Horários publicados.',
            'docs' => [$key => ['status' => 'pending', 'link' => 'eser.exemplo/doc/1']],
            'files' => [$key => UploadedFile::fake()->create('horario.pdf', 100, 'application/pdf')],
        ])->assertRedirect(route('audit.courses.show', $this->course) . '#ind-1-12-1');

        $doc = CourseAuditDocument::where('doc_key', $key)->firstOrFail();
        $this->assertSame('delivered', $doc->status);
        $this->assertSame('https://eser.exemplo/doc/1', $doc->link);
        $this->assertSame('horario.pdf', $doc->file_name);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->actingAs($this->coordinator)->get(route('audit.courses.download', [$this->course, $doc]))->assertOk();

        // Nota 2 num imprescindível (1.1) e 4 no 2.1: média ponderada (peso 2 cada) + grupo 1.12 (peso 1, SIM = 5).
        $this->actingAs($this->coordinator)->post(route('audit.courses.indicator', [$this->course, '1.1']), ['score' => 2]);
        $this->actingAs($this->coordinator)->post(route('audit.courses.indicator', [$this->course, '2.1']), ['score' => 4]);

        $result = app(CourseAuditCatalog::class)->evaluate(CourseAudit::first()->load(['entries', 'documents']));
        $this->assertEqualsWithDelta((2 * 2 + 4 * 2 + 5 * 1) / 5, $result['score'], 0.01);
        $this->assertSame('Gestão I', $result['band']); // 3,40
        $this->assertCount(1, $result['alerts']);
        $this->assertSame(1, $result['documents']['delivered']);
    }

    public function test_not_applicable_leaves_the_indicator_out(): void
    {
        $this->actingAs($this->coordinator)->post(route('audit.courses.indicator', [$this->course, '1.1']), ['score' => 5]);
        $this->actingAs($this->coordinator)->post(route('audit.courses.indicator', [$this->course, '3.3']), ['score' => 1, 'not_applicable' => 1]);

        $audit = CourseAudit::first()->load(['entries', 'documents']);
        $result = app(CourseAuditCatalog::class)->evaluate($audit);
        $this->assertEquals(5.0, $result['score']);
        $this->assertNull($audit->entries->firstWhere('indicator', '3.3')->score);
    }

    public function test_invalid_score_for_yes_no_items_is_rejected(): void
    {
        $this->actingAs($this->coordinator)->post(route('audit.courses.indicator', [$this->course, '1.11.1']), ['score' => 3])
            ->assertSessionHasErrors('score');
    }

    public function test_assistant_edits_only_his_course_and_staff_only_reads(): void
    {
        $staff = User::create(['name' => 'Secretaria', 'email' => 's@x.local', 'password' => 'senha1234', 'role' => 'staff', 'is_active' => true]);
        $other = Course::create(['code' => 'ADS', 'name' => 'ADS']);

        $this->actingAs($staff)->get(route('audit.courses.show', $this->course))->assertOk()->assertSee('Somente consulta');
        $this->actingAs($staff)->post(route('audit.courses.indicator', [$this->course, '1.1']), ['score' => 5])->assertForbidden();

        $this->course->assistants()->attach($staff->id);
        $this->actingAs($staff)->post(route('audit.courses.indicator', [$this->course, '1.1']), ['score' => 5])->assertRedirect();
        $this->actingAs($staff)->post(route('audit.courses.indicator', [$other, '1.1']), ['score' => 5])->assertForbidden();

        $teacher = User::create(['name' => 'Prof', 'email' => 'p@x.local', 'password' => 'senha1234', 'role' => 'teacher', 'is_active' => true]);
        $this->actingAs($teacher)->get(route('audit.courses.index'))->assertForbidden();
    }

    public function test_closed_term_is_read_only(): void
    {
        AcademicTerm::first()->update(['status' => 'closed']);
        $this->actingAs($this->coordinator)->get(route('audit.courses.show', $this->course))->assertOk()->assertSee('Somente consulta');
        $this->actingAs($this->coordinator)->post(route('audit.courses.indicator', [$this->course, '1.1']), ['score' => 5])->assertForbidden();
    }
}
