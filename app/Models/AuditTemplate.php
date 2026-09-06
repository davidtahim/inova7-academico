<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditTemplate extends Model { protected $fillable = ['audit_item_id','code','name','version','shift','approved_by','approved_at','file_path','mime_type','markers','is_current']; protected function casts(): array { return ['approved_at'=>'date','markers'=>'array','is_current'=>'boolean']; } public function item(){ return $this->belongsTo(AuditItem::class,'audit_item_id'); } }
