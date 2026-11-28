<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ClassOffering extends Model {
    protected $fillable = ['academic_term_id','course_id','curriculum_matrix_id','curriculum_matrix_discipline_id','subject_id','class_code','period','shift','modality','student_count','directed_study','occurs','weekly_hours','classes_per_week','totvs_hours','status'];
    protected function casts(): array { return ['occurs'=>'boolean','directed_study'=>'boolean','weekly_hours'=>'decimal:2','totvs_hours'=>'decimal:2']; }
    public function term(){ return $this->belongsTo(AcademicTerm::class, 'academic_term_id'); }
    public function course(){ return $this->belongsTo(Course::class); }
    public function subject(){ return $this->belongsTo(Subject::class); }
    public function slots(){ return $this->hasMany(ScheduleSlot::class); }
    public function assignments(){ return $this->hasMany(TeachingAssignment::class); }
    public function enrollments(){ return $this->hasMany(ClassEnrollment::class); }

    /** Modalidades sem aula na grade física (a distância / nacionais). */
    public const DISTANCE_MODALITIES = ['DOL', 'NAVEGA', 'NOTÁVEL MESTRE'];

    public function isDistance(): bool
    {
        return in_array(mb_strtoupper((string) $this->modality), self::DISTANCE_MODALITIES, true);
    }

    /** Aulas a distribuir na grade: a da oferta, senão a da matriz. Zero para EaD. */
    public function classesToSchedule(): int
    {
        if ($this->isDistance()) {
            return 0;
        }

        return (int) ($this->classes_per_week ?? $this->matrixDiscipline?->weekly_hours ?? 0);
    }
    
    /**
     * Obter o vínculo de matriz-disciplina que originou esta oferta.
     */
    public function matrixDiscipline(){
        return $this->belongsTo(CurriculumMatrixDiscipline::class, 'curriculum_matrix_discipline_id');
    }
    
    /**
     * Validação Hard Constraint 1: Verificar se professor tem disponibilidade.
     */
    public function validateProfessorAvailability(Professor $professor): bool
    {
        if (!$this->term) {
            return false;
        }
        
        $availability = ProfessorAvailability::where('professor_id', $professor->id)
            ->where('academic_term_id', $this->term->id)
            ->where('weekday', $this->getWeekdayFromShift())
            ->where('preference', '!=', 'unavailable')
            ->exists();
        
        return $availability;
    }
    
    /**
     * Validação Hard Constraint 2: Verificar se professor já está alocado neste dia/horário.
     */
    public function validateNoScheduleConflict(Professor $professor): bool
    {
        return !ScheduleSlot::where('professor_id', $professor->id)
            ->whereHas('classOffering', function ($query) {
                $query->where('academic_term_id', $this->academic_term_id);
            })
            ->where('weekday', $this->getWeekdayFromShift())
            ->exists();
    }
    
    /**
     * Validação Hard Constraint 3: Verificar se professor não ultrapassa carga horária contratual.
     */
    public function validateMaxWeeklyHours(Professor $professor, int $maxAllowedHours = 20): bool
    {
        $assignedHours = TeachingAssignment::where('professor_id', $professor->id)
            ->whereHas('classOffering', function ($query) {
                $query->where('academic_term_id', $this->academic_term_id);
            })
            ->sum('weekly_hours');
        
        return ($assignedHours + $this->weekly_hours) <= $maxAllowedHours;
    }
    
    /**
     * Helper para extrair weekday do shift (simplificado).
     */
    private function getWeekdayFromShift(): int
    {
        // Retorna 0-6 (segunda a domingo). Para agora, retorna padrão.
        // Em produção, seria derivado de alguma configuração de horário.
        return 1; // Segunda-feira por padrão
    }
}
