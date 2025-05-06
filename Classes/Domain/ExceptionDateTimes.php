<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * @see https://icalendar.org/iCalendar-RFC-5545/3-8-5-1-exception-date-times.html
 * @implements \IteratorAggregate<int,\DateTimeImmutable>
 */
#[Flow\Proxy(false)]
final readonly class ExceptionDateTimes implements \JsonSerializable, \Stringable, \IteratorAggregate
{
    /**
     * @var array<int,\DateTimeImmutable>
     */
    private array $items;

    private function __construct(\DateTimeImmutable ...$items)
    {
        $this->items = array_values($items);
    }

    public static function create(\DateTimeImmutable ...$items): self
    {
        return new self(...$items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @return \Traversable<int,\DateTimeImmutable>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    /**
     * @return array<int,\DateTimeImmutable>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    public function toString(): string
    {
        return 'EXDATE;' . implode(',', array_map(
            fn (\DateTimeImmutable $date): string => 'TZID=' . $date->getTimezone()->getName() . ':' . $date->format('YmdTHis'),
            $this->items
        ));
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
