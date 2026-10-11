<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'Pending';
    case Confirmed = 'Confirmed';
    case CheckedIn = 'Checked In';
    case CheckedOut = 'Checked Out';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';
    case NoShow = 'No Show';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    /** @return list<string> */
    public static function active(): array
    {
        return [self::Pending->value, self::Confirmed->value, self::CheckedIn->value];
    }
}
