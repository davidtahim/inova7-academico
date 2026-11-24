<?php

namespace App\Enums;

enum Shift: string
{
    case MANHÃ = 'MANHÃ';
    case TARDE = 'TARDE';
    case NOITE = 'NOITE';
    case INTEGRAL = 'INTEGRAL';

    public function label(): string
    {
        return match ($this) {
            self::MANHÃ => 'Manhã (07:30 - 12:40) — 6 aulas',
            self::TARDE => 'Tarde (13:10 - 17:30) — 5 aulas',
            self::NOITE => 'Noite (18:30 - 22:00) — 4 aulas',
            self::INTEGRAL => 'Integral (07:30 - 17:30) — Manhã e Tarde',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MANHÃ => '#FFA500',    // Laranja
            self::TARDE => '#FF6B6B',    // Vermelho
            self::NOITE => '#6C63FF',    // Roxo
            self::INTEGRAL => '#4ECDC4', // Teal
        };
    }

    public static function options(): array
    {
        return [
            self::MANHÃ->value => self::MANHÃ->label(),
            self::TARDE->value => self::TARDE->label(),
            self::NOITE->value => self::NOITE->label(),
            self::INTEGRAL->value => self::INTEGRAL->label(),
        ];
    }
}
