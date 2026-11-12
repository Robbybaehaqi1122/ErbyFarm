<?php

namespace App\Modules\User\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    /**
     * Human readable label used by the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin / Owner',
            self::Operator => 'Operator Kandang',
            self::Viewer => 'Viewer / Staff',
        };
    }

    /**
     * All role values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
