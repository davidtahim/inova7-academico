<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AcademicTerm extends Model { protected $fillable = ['code','starts_at','ends_at','status']; protected function casts(): array { return ['starts_at'=>'date','ends_at'=>'date']; } }
