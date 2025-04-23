<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Sitegeist\GroundhogDay\Domain\Recurrence\RecurrenceRule;

class RecurrenceRuleToStringConverter extends AbstractTypeConverter
{
    /**
     * @var array<int,string>
     */
    protected $sourceTypes = [RecurrenceRule::class];

    /**
     * @var string
     * @api
     */
    protected $targetType = 'string';

    /**
     * @var integer
     */
    protected $priority = 10;

    /**
     * @param RecurrenceRule $source
     * @param string $targetType,
     * @param array<mixed> $convertedChildProperties
     * @return string
     */
    public function convertFrom(
        $source,
        $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ) {
        return $source->toString();
    }
}
