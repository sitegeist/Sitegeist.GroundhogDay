<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * @see https://icalendar.org/iCalendar-RFC-5545/3-8-5-1-exception-date-times.html
 * @implements \IteratorAggregate<int,DateTimeSpecification>
 */
#[Flow\Proxy(false)]
final readonly class ExceptionDateTimes implements \JsonSerializable, \IteratorAggregate
{
    /**
     * @var array<int,DateTimeSpecification>
     */
    private array $items;

    private function __construct(DateTimeSpecification ...$items)
    {
        $this->items = array_values($items);
    }

    public static function create(DateTimeSpecification ...$items): self
    {
        return new self(...$items);
    }

    public static function fromString(string $value): self
    {
        $values = [];
        foreach (\explode(',', \mb_substr($value, 7)) as $part) { // EXDATE:
            $values[] = DateTimeSpecification::fromString($part);
        }

        return new self(...$values);
    }

    /**
     * @param array<string> $values
     */
    public static function fromArray(array $values): self
    {
        return new self(...array_map(
            fn (string $value): DateTimeSpecification => DateTimeSpecification::fromString($value),
            $values
        ));
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @return \Traversable<int,DateTimeSpecification>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    public function jsonSerialize(): string
    {
        return 'EXDATE:' . implode(',', array_map(
            fn (DateTimeSpecification $date): string => $date->value,
            $this->items
        ));
    }

    public function toString(\DateTimeZone $locationTimezone): string
    {
        return 'EXDATE;' . implode(',', array_map(
            fn (DateTimeSpecification $date): string => 'TZID=' . $locationTimezone->getName() . ':' . $date->value,
            $this->items
        ));
    }
}
