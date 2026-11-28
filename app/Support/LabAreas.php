<?php

namespace App\Support;

/**
 * Áreas de laboratórios e os tipos de sala de cada uma. Cada área tem seu gestor
 * (perfil "Gestor de laboratórios" + área) e seu painel de reservas.
 */
class LabAreas
{
    public const AREAS = [
        'ti' => ['label' => 'TI (NTI)', 'types' => ['LABORATORIO_INFORMATICA']],
        'saude' => ['label' => 'Saúde', 'types' => ['LABORATORIO_SAUDE', 'LABORATORIO_CIENCIAS']],
        'comunicacao' => ['label' => 'Comunicação', 'types' => ['LABORATORIO_COMUNICACAO', 'ESTUDIO_PRODUCAO']],
        'engenharia' => ['label' => 'Engenharias', 'types' => ['LABORATORIO_ENGENHARIA']],
        'arquitetura' => ['label' => 'Arquitetura', 'types' => ['LABORATORIO_ARQUITETURA']],
    ];

    /** Todos os tipos de sala que são laboratório de alguma área. */
    public static function labTypes(): array
    {
        return array_merge(...array_column(self::AREAS, 'types'));
    }

    public static function isLabType(?string $type): bool
    {
        return in_array($type, self::labTypes(), true);
    }

    public static function areaOfType(?string $type): ?string
    {
        foreach (self::AREAS as $key => $area) {
            if (in_array($type, $area['types'], true)) {
                return $key;
            }
        }

        return null;
    }

    public static function label(?string $area): string
    {
        return self::AREAS[$area]['label'] ?? '—';
    }

    public static function types(string $area): array
    {
        return self::AREAS[$area]['types'] ?? [];
    }

    public static function options(): array
    {
        return array_map(fn ($a) => $a['label'], self::AREAS);
    }
}
