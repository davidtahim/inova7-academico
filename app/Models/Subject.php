<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Subject extends Model { protected $fillable = ['code','name','total_hours','presential_hours','online_hours','practice_hours','extension_hours']; }
