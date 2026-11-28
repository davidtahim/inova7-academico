<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public const TYPES = [
        'SALA_COMUM' => 'Sala comum',
        'LABORATORIO_INFORMATICA' => 'Laboratório de Informática',
        'LABORATORIO_ENGENHARIA' => 'Laboratório de Engenharia',
        'SALA_PRATICA' => 'Sala de prática',
        'ESTUDIO_PRODUCAO' => 'Estúdio de produção',
        'LABORATORIO_CIENCIAS' => 'Laboratório de Ciências',
    ];

    protected $fillable = ['code', 'name', 'type', 'capacity', 'computers', 'softwares', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'capacity' => 'integer', 'computers' => 'integer'];
    }

    public function isComputerLab(): bool
    {
        return $this->type === 'LABORATORIO_INFORMATICA';
    }

    /** Softwares instalados, normalizados (sem acento, minúsculas). */
    public function softwareList(): array
    {
        return \App\Services\LabRules::softwareList($this->softwares);
    }

    /** "Li30" → laboratório de informática; demais → sala comum. */
    public static function guessType(string $code): string
    {
        return preg_match('/^li\d/i', trim($code)) === 1 ? 'LABORATORIO_INFORMATICA' : 'SALA_COMUM';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
