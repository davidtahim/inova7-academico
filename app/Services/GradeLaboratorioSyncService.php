<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassOffering;
use App\Models\LabReservation;
use App\Models\Room;
use App\Models\User;

/**
 * Sincroniza a grade horária com as reservas de laboratório (Art. 16 I): cada aula semanal
 * de uma turma num laboratório de informática vira uma reserva recorrente, aprovada, no
 * semestre inteiro. Ao mudar ou tirar a aula da grade, a reserva é atualizada ou cancelada,
 * liberando o laboratório no painel do NTI.
 *
 * Chamado pelo ScheduleAllocator::apply (montagem, grade global, resolvedor, versões).
 */
class GradeLaboratorioSyncService
{
    public function syncOffering(ClassOffering $offering, ?User $actor = null): void
    {
        $offering->loadMissing(['slots', 'term', 'assignments']);
        $term = $offering->term;
        $labs = Room::where('type', LabRules::LAB)->get()->keyBy(fn ($r) => mb_strtolower($r->code));
        $actor ??= auth()->user();

        $existing = LabReservation::where('turma_id', $offering->id)->where('origem', 'grade')->get()
            ->keyBy(fn ($r) => $r->laboratorio_id . '|' . $r->dia_semana . '|' . $r->start());

        $keep = [];
        foreach ($offering->slots as $slot) {
            $lab = $labs->get(mb_strtolower((string) $slot->room));
            if (! $lab) {
                continue;
            }
            $start = substr((string) $slot->starts_at, 0, 5);
            $key = $lab->id . '|' . $slot->weekday . '|' . $start;
            $professorUser = $slot->professor_id ? User::where('professor_id', $slot->professor_id)->value('id') : null;

            $data = [
                'academic_term_id' => $term?->id,
                'laboratorio_id' => $lab->id,
                'turma_id' => $offering->id,
                'solicitante_id' => $professorUser ?? $actor?->id,
                'tipo_solicitacao' => 'Aula',
                'origem' => 'grade',
                'titulo' => ($offering->subject?->name ?? 'Aula') . ' · ' . $offering->class_code,
                'dia_semana' => (int) $slot->weekday,
                'data' => null,
                'hora_inicio' => $start,
                'hora_fim' => substr((string) $slot->ends_at, 0, 5),
                'turno' => $offering->shift,
                'recorrente' => true,
                'frequencia' => 'Semanal',
                'data_inicio' => $term?->starts_at,
                'data_fim' => $term?->ends_at,
                'participantes' => $offering->student_count,
                'status' => 'Aprovada',
            ];

            $reservation = $existing->get($key);
            $reservation ? $reservation->fill($data)->save() : $reservation = LabReservation::create($data);
            $keep[] = $reservation->id;
        }

        // Aula tirada ou movida: a reserva antiga é cancelada (o laboratório fica livre no painel).
        LabReservation::where('turma_id', $offering->id)->where('origem', 'grade')
            ->whereNotIn('id', $keep)->where('status', '!=', 'Cancelada')
            ->update(['status' => 'Cancelada', 'observacoes' => 'Cancelada automaticamente: aula retirada ou movida na grade.']);
    }

    /** Ressincroniza o semestre inteiro (botão do NTI; também após a migration). */
    public function syncTerm(AcademicTerm $term, ?User $actor = null): int
    {
        $count = 0;
        ClassOffering::with(['slots', 'term', 'subject'])->where('academic_term_id', $term->id)
            ->chunkById(100, function ($offerings) use (&$count, $actor) {
                foreach ($offerings as $offering) {
                    $this->syncOffering($offering, $actor);
                    $count++;
                }
            });

        return $count;
    }
}
