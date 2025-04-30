<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Tests\Unit\Domain\Recurrence;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceDates;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceDatesAreChanged;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRuleIsChanged;

class RecurrenceDatesAreChangedTest extends TestCase
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
            RecurrenceDatesAreChanged::isSatisfiedByEventOccurrenceSpecifications($oldSpecification, $newSpecification)
        );
    }

    public static function specificationProvider(): iterable
    {
        yield 'no old specification, new specification without rdate' => [
            'oldSpecification' => null,
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                null,
            ),
            'expectedValue' => false,
        ];

        yield 'no old specification, new specification with empty rdate' => [
            'oldSpecification' => null,
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                RecurrenceDates::create(),
            ),
            'expectedValue' => false,
        ];

        yield 'old specification without rdate, new specification without rdate' => [
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

        yield 'old specification without rdate, new specification with empty rdate' => [
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
                RecurrenceDates::create(),
            ),
            'expectedValue' => false,
        ];

        yield 'old specification with empty rdate, new specification without rdate' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:43:30'),
                null,
                null,
                RecurrenceDates::create(),
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                null,
            ),
            'expectedValue' => false,
        ];

        yield 'old specification with empty rdate, new specification with empty rdate' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:43:30'),
                null,
                null,
                RecurrenceDates::create(),
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                RecurrenceDates::create(),
            ),
            'expectedValue' => false,
        ];

        yield 'no old specification, new specification with rdate' => [
            'oldSpecification' => null,
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'expectedValue' => true,
        ];

        yield 'old specification without rdate, new specification with rdate' => [
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
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with empty rdate, new specification with rdate' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:43:30'),
                null,
                null,
                RecurrenceDates::create(),
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with rdate, new specification with the same rdate' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-30 15:43:30'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'expectedValue' => false,
        ];

        yield 'old specification with rdate, new specification with different rdate' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-02 12:30:00'),
                ),
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with rdate, new specification with additional rdate' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                    self::createDateTime('2025-05-02 12:30:00'),
                ),
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with rdate, new specification without rdate' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                null,
                null,
                null,
            ),
            'expectedValue' => true,
        ];

        yield 'old specification with rdate, new specification with empty rdate' => [
            'oldSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                null,
                null,
                RecurrenceDates::create(
                    self::createDateTime('2025-05-01 12:30:00'),
                ),
            ),
            'newSpecification' => new EventOccurrenceSpecification(
                self::createDateTime('2025-04-29 15:30:00'),
                null,
                null,
                RecurrenceDates::create(),
            ),
            'expectedValue' => true,
        ];
    }

    private static function createDateTime(string $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);
    }
}
