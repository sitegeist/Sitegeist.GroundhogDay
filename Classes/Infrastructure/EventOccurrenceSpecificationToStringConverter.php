<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;

class EventOccurrenceSpecificationToStringConverter extends AbstractTypeConverter
{
    /**
     * @var array<int,string>
     */
    protected $sourceTypes = [EventOccurrenceSpecification::class];

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
     * @param EventOccurrenceSpecification $source
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
