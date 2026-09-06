<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditDocument extends Model { protected $fillable = ['audit_template_id','academic_term_id','course_id','generated_by','class_code','period','shift','revision','status','file_path','data_snapshot','issued_at']; protected function casts(): array { return ['data_snapshot'=>'array','issued_at'=>'datetime']; } public function template(){ return $this->belongsTo(AuditTemplate::class,'audit_template_id'); } }
