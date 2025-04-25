<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRuleIsInvalid;

class StringToRecurrenceRuleConverter extends AbstractTypeConverter
{
    /**
     * @var array<int,string>
     */
    protected $sourceTypes = ['string'];

    /**
     * The target type this converter can convert to.
     *
     * @var string
     * @api
     */
    protected $targetType = RecurrenceRule::class;

    /**
     * @var integer
     */
    protected $priority = 1;

    /**
     * @param string $source
     * @param string $targetType,
     * @param array<mixed> $convertedChildProperties
     * @return ?RecurrenceRule
     */
    public function convertFrom(
        $source,
        $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ) {
        try {
            return RecurrenceRule::fromString($source);
        } catch (RecurrenceRuleIsInvalid) {
            return null;
        }
    }
}
