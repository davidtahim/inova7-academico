<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeachingAssignment extends Model { protected $fillable = ['class_offering_id','professor_id','weekly_hours','status']; public function professor(){ return $this->belongsTo(Professor::class); } public function offering(){ return $this->belongsTo(ClassOffering::class, 'class_offering_id'); } }
