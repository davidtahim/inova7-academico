<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reserva de laboratório de informática (módulo do NTI).
 * origem = grade: criada/atualizada/cancelada automaticamente pela grade horária
 * (GradeLaboratorioSyncService); origem = manual: pedidos avulsos, eventos e provas.
 */
class LabReservation extends Model
{
    protected $table = 'reservas_laboratorio';

    public const TYPES = ['Aula', 'Avulsa', 'Evento', 'Prova', 'Monitoria'];

    public const STATUSES = ['Pendente', 'Aprovada', 'Recusada', 'Cancelada'];

    protected $fillable = [
        'academic_term_id', 'laboratorio_id', 'turma_id', 'solicitante_id', 'aprovado_por', 'tipo_solicitacao', 'origem',
        'titulo', 'dia_semana', 'data', 'hora_inicio', 'hora_fim', 'turno', 'recorrente', 'frequencia',
        'data_inicio', 'data_fim', 'participantes', 'status', 'observacoes',
    ];

    protected function casts(): array
    {
        return ['recorrente' => 'boolean', 'data' => 'date', 'data_inicio' => 'date', 'data_fim' => 'date', 'dia_semana' => 'integer', 'participantes' => 'integer'];
    }

    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'laboratorio_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(ClassOffering::class, 'turma_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'Aprovada');
    }

    public function isFromGrade(): bool
    {
        return $this->origem === 'grade';
    }

    public function start(): string
    {
        return substr((string) $this->hora_inicio, 0, 5);
    }

    public function end(): string
    {
        return substr((string) $this->hora_fim, 0, 5);
    }

    /** Vale numa data? (recorrente semanal dentro do período, ou pontual naquele dia). */
    public function occursOn(\Carbon\CarbonInterface $date): bool
    {
        if ($this->recorrente) {
            return (int) $this->dia_semana === (int) $date->dayOfWeekIso
                && (! $this->data_inicio || $date->gte($this->data_inicio->startOfDay()))
                && (! $this->data_fim || $date->lte($this->data_fim->endOfDay()));
        }

        return $this->data?->isSameDay($date) ?? false;
    }
}
