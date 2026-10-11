<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Available = 'Available';
    case Reserved = 'Reserved';
    case Occupied = 'Occupied';
    case Cleaning = 'Cleaning';
    case Maintenance = 'Maintenance';
    case OutOfOrder = 'Out of Order';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    /** @return list<string> */
    public static function manuallyManaged(): array
    {
        return [self::Available->value, self::Cleaning->value, self::Maintenance->value, self::OutOfOrder->value];
    }
}
