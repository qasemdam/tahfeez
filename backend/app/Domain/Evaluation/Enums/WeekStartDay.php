<?php

namespace App\Domain\Evaluation\Enums;

use InvalidArgumentException;

/**
 * The configured first day of the evaluation week (BR-TEN-04). It is center
 * configuration, never a hard-coded Saturday.
 */
enum WeekStartDay: string
{
    case Saturday = 'saturday';
    case Sunday = 'sunday';
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';

    /**
     * ISO-8601 weekday number: Monday = 1 … Sunday = 7.
     */
    public function isoWeekday(): int
    {
        return match ($this) {
            self::Monday => 1,
            self::Tuesday => 2,
            self::Wednesday => 3,
            self::Thursday => 4,
            self::Friday => 5,
            self::Saturday => 6,
            self::Sunday => 7,
        };
    }

    /**
     * The day a date actually falls on. Used where a stored boundary has to be
     * described without consulting the center's current setting.
     */
    public static function fromIsoWeekday(int $isoWeekday): self
    {
        foreach (self::cases() as $case) {
            if ($case->isoWeekday() === $isoWeekday) {
                return $case;
            }
        }

        throw new InvalidArgumentException(sprintf('Invalid ISO weekday [%d].', $isoWeekday));
    }
}
