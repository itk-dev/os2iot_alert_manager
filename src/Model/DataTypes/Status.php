<?php

namespace App\Model\DataTypes;

enum Status: string
{
    case NONE = 'NONE';
    case IN_OPERATION = 'IN-OPERATION';
    case PROTOTYPE = 'PROTOTYPE';
    case PROJECT = 'PROJECT';
    case OTHER = 'OTHER';

    public static function array(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_column(self::cases(), 'name'));
    }
}
