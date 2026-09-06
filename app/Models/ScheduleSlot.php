<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScheduleSlot extends Model { protected $fillable = ['class_offering_id','professor_id','weekday','starts_at','ends_at','room','block']; public function offering(){ return $this->belongsTo(ClassOffering::class, 'class_offering_id'); } public function professor(){ return $this->belongsTo(Professor::class); } }
