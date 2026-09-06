<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Professor extends Model { protected $fillable = ['registration','name','email','qualification','active']; protected function casts(): array { return ['active'=>'boolean']; } }
