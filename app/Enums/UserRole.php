<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Salesperson = 'salesperson';
    case Warehouse = 'warehouse';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
