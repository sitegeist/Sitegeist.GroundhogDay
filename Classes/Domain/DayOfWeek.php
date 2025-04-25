<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

/** @see https://schema.org/DayOfWeek */
enum DayOfWeek: string
{
    case DAY_MONDAY = 'https://schema.org/Monday';
    case DAY_TUESDAY = 'https://schema.org/Tuesday';
    case DAY_WEDNESDAY = 'https://schema.org/Wednesday';
    case DAY_THURSDAY = 'https://schema.org/Thursday';
    case DAY_FRIDAY = 'https://schema.org/Friday';
    case DAY_SATURDAY = 'https://schema.org/Saturday';
    case DAY_SUNDAY = 'https://schema.org/Sunday';
    case DAY_PUBLIC_HOLIDAYS = 'https://schema.org/PublicHolidays';

    public static function fromDate(\DateTimeInterface $date): self
    {
        return match ($date->format('w')) {
            '0' => self::DAY_SUNDAY,
            '1' => self::DAY_MONDAY,
            '2' => self::DAY_TUESDAY,
            '3' => self::DAY_WEDNESDAY,
            '4' => self::DAY_THURSDAY,
            '5' => self::DAY_FRIDAY,
            '6' => self::DAY_SATURDAY,
        };
    }
}
