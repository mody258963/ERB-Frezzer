<?php

namespace App\Enums;

enum PartUnit: string
{
    case Piece = 'pc';
    case Box = 'box';
    case Set = 'set';
    case Kilogram = 'kg';
    case Meter = 'm';
    case Liter = 'l';
    case Roll = 'roll';
    case Pack = 'pack';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Piece',
            self::Box => 'Box',
            self::Set => 'Set',
            self::Kilogram => 'Kilogram',
            self::Meter => 'Meter',
            self::Liter => 'Liter',
            self::Roll => 'Roll',
            self::Pack => 'Pack',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $unit) => ['value' => $unit->value, 'label' => $unit->label()],
            self::cases()
        );
    }
}
