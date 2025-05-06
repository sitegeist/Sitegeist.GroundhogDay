<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * @implements \IteratorAggregate<int,EventOccurrence>
 */
#[Flow\Proxy(false)]
final readonly class EventOccurrences implements \IteratorAggregate
{
    /** @var list<EventOccurrence> */
    private array $items;

    public function __construct(
        EventOccurrence ...$items
    ) {
        $this->items = array_values($items);
    }

    public static function create(EventOccurrence ...$items): self
    {
        return new self(...$items);
    }

    /**
     * @return \Traversable<int,EventOccurrence>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }
}
