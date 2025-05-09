<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * A date time specification, relative to the event's venue's local time zone.
 */
#[Flow\Proxy(false)]
final readonly class DateTimeSpecification implements \JsonSerializable, \Stringable
{
    /**
     * @see https://icalendar.org/iCalendar-RFC-5545/3-3-5-date-time.html Form #1
     */
    public const DATE_FORMAT = 'Ymd\THis';

    public function __construct(
        public string $value,
    ) {
        $dateTime = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value);
        if ($dateTime === false) {
            throw new \InvalidArgumentException('Invalid date string ' . $value . ', must be in format ' . self::DATE_FORMAT, 1746788284);
        }
    }

    public static function create(string $value): self
    {
        return new self($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    /**
     * @param \DateTimeZone $dateTimeZone The event's venue's date time zone, or e.g. UTC for global online events
     */
    public function toDateTime(\DateTimeZone $dateTimeZone): \DateTimeImmutable
    {
        $result = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->value, $dateTimeZone);
        assert($result instanceof \DateTimeImmutable); // wouldn't have the constructor otherwise

        return $result;
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
