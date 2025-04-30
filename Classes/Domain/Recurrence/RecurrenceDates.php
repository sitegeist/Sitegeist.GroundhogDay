<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain\Recurrence;

use Neos\Flow\Annotations as Flow;

/**
 * @implements \IteratorAggregate<int,\DateTimeImmutable>
 */
#[Flow\Proxy(false)]
final readonly class RecurrenceDates implements \JsonSerializable, \Stringable, \IteratorAggregate
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
        return 'RDATE;' . implode(',', array_map(
            fn (\DateTimeImmutable $date): string => 'TZID=' . $date->getTimezone()->getName() . ':' . $date->format('YmdTHis'),
            $this->items
        ));
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
