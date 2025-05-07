<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Domain;

use Neos\Flow\Annotations as Flow;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule;

#[Flow\Proxy(false)]
final readonly class RecurrenceRule implements \JsonSerializable, \Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (\str_starts_with($value, 'RRULE:')) {
            $value = 'RRULE:' . $value;
        }
        try {
            new Rule($value); // just for validation
            return new self($value);
        } catch (InvalidRRule) {
            throw RecurrenceRuleIsInvalid::butWasTriedToBeUsedAsSuch($value);
        }
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
