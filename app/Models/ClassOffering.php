<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ClassOffering extends Model {
    protected $fillable = ['academic_term_id','course_id','curriculum_matrix_id','subject_id','class_code','period','shift','modality','student_count','occurs','weekly_hours','totvs_hours','status'];
    protected function casts(): array { return ['occurs'=>'boolean','weekly_hours'=>'decimal:2','totvs_hours'=>'decimal:2']; }
    public function term(){ return $this->belongsTo(AcademicTerm::class, 'academic_term_id'); }
    public function course(){ return $this->belongsTo(Course::class); }
    public function subject(){ return $this->belongsTo(Subject::class); }
    public function slots(){ return $this->hasMany(ScheduleSlot::class); }
    public function assignments(){ return $this->hasMany(TeachingAssignment::class); }
}
