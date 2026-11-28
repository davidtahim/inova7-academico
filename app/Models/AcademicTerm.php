<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AcademicTerm extends Model { protected $fillable = ['code','starts_at','ends_at','status','schedule_published_at','schedule_published_by']; protected function casts(): array { return ['starts_at'=>'date','ends_at'=>'date','schedule_published_at'=>'datetime']; }
    public function isSchedulePublished(): bool { return $this->schedule_published_at !== null; }
    public function publisher() { return $this->belongsTo(User::class, 'schedule_published_by'); }
    public function offerings() { return $this->hasMany(ClassOffering::class); }

    public const STATUS_LABELS = ['planning' => 'Em planejamento', 'active' => 'Em andamento', 'closed' => 'Concluído'];

    /** Semestre concluído: somente consulta (grade, disponibilidade, enturmação). */
    public function isClosed(): bool { return $this->status === 'closed'; }

    public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? (string) $this->status; }

    /**
     * Semestre de trabalho, igual em todas as telas: o escolhido no seletor do topo;
     * sem escolha, o semestre em planejamento mais recente; por último, o mais recente
     * pelo código (2027.1 > 2026.2). Antes cada tela caía no "último cadastrado" (id),
     * e o professor podia preencher a disponibilidade de um semestre já encerrado.
     */
    public static function current(): ?self
    {
        $selected = session('selected_academic_term_id');
        $term = ($selected ? static::find($selected) : null)
            ?? static::where('status', 'planning')->orderByDesc('code')->first()
            ?? static::orderByDesc('code')->first();

        if ($term && (int) $selected !== (int) $term->id) {
            session(['selected_academic_term_id' => $term->id]);
        }

        return $term;
    }
}
