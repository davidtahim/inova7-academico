<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditItem extends Model { protected $fillable = ['axis','code','name','weight','criteria','eser_category']; public function templates(){ return $this->hasMany(AuditTemplate::class); } }
