<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Tests\Unit\Domain\Recurrence;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceDates;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRuleIsChanged;

class RecurrenceRuleIsChangedTest extends TestCase
{
    /**
     * @dataProvider specificationProvider
     */
    public function testIsSatisfiedByEventOccurrenceSpecifications(
        ?EventOccurrenceSpecification $oldSpecification,
        EventOccurrenceSpecification $newSpecification,
        bool $expectedValue,
    ): void {
        Assert::assertSame(
            $expectedValue,
            RecurrenceRuleIsChanged::isSatisfiedByEventOccurrenceSpecifications($oldSpecification, $newSpecification)
        );
    }

    public static function specificationProvider(): iterable
    {
        yield 'no old specification, new specification without rrule' => [
            'oldSpecification' => null,
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                null,
            ),
            'expectedValue' => false,
        ];

        yield 'old specification without rrule, new specification without rrule' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:43:30'),
                null,
                null,
                null,
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                null,
            ),
            'expectedValue' => false,
        ];

        yield 'no old specification, new specification with rrule' => [
            'oldSpecification' => null,
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'expectedValue' => true,
        ];

        yield 'old specification without rrule, new specification with rrule' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:43:30'),
                null,
                null,
                null,
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with rrule, new specification with same rrule etc' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                RecurrenceDates::create(
                    new \DateTimeImmutable()
                ),
            ),
            'expectedValue' => false,
        ];

        yield 'old specification with rrule, new specification with same rrule but different start date' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:43:30'),
                null,
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with rrule, new specification with same rrule but different end date' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                self::createDateTime('2025-04-29 16:30:00'),
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:30:00'),
                self::createDateTime('2025-04-30 16:15:00'),
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with rrule, new specification with different rrule' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                self::createDateTime('2025-04-29 16:30:00'),
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                self::createDateTime('2025-04-29 16:30:00'),
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=2'),
                null,
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with rrule, new specification without rrule' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                self::createDateTime('2025-04-29 16:30:00'),
                RecurrenceRule::fromString('FREQ=DAILY;INTERVAL=1'),
                null,
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                self::createDateTime('2025-04-29 16:30:00'),
                null,
                null,
            ),
            'expectedValue' => true,
        ];
    }

    private static function createDateTime(string $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);
    }
}
