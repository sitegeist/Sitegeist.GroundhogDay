<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;

class EventOccurrenceSpecificationToArrayConverter extends AbstractTypeConverter
{
    /**
     * @var array<int,string>
     */
    protected $sourceTypes = [EventOccurrenceSpecification::class];

    /**
     * @var string
     * @api
     */
    protected $targetType = 'array';

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
        return \json_encode($source, JSON_THROW_ON_ERROR);
    }
}
