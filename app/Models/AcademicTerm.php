<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AcademicTerm extends Model { protected $fillable = ['code','starts_at','ends_at','status','schedule_published_at','schedule_published_by']; protected function casts(): array { return ['starts_at'=>'date','ends_at'=>'date','schedule_published_at'=>'datetime']; }
    public function isSchedulePublished(): bool { return $this->schedule_published_at !== null; }
    public function publisher() { return $this->belongsTo(User::class, 'schedule_published_by'); }
    public function offerings() { return $this->hasMany(ClassOffering::class); }
}
