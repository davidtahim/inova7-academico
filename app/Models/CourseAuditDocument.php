<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Situação de um documento exigido pela auditoria: pendente, entregue (arquivo/link) ou não se aplica. */
class CourseAuditDocument extends Model
{
    public const STATUSES = ['pending' => 'Pendente', 'delivered' => 'Entregue', 'na' => 'Não se aplica'];

    protected $fillable = ['course_audit_id', 'indicator', 'doc_key', 'name', 'status', 'link', 'file_path', 'file_name', 'responsible_id', 'due_date', 'delivered_at', 'updated_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'delivered_at' => 'datetime'];
    }

    public static function keyFor(string $indicator, string $name): string
    {
        return substr(sha1($indicator . '|' . $name), 0, 16);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
