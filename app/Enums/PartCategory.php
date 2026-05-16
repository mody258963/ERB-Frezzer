<?php

namespace App\Enums;

enum PartCategory: string
{
    case Compressor = 'Compressor';
    case Evaporator = 'Evaporator';
    case FanMotor = 'Fan Motor';
    case Controls = 'Controls';
    case Electrical = 'Electrical';
    case Refrigerant = 'Refrigerant';
    case Seals = 'Seals';
}
