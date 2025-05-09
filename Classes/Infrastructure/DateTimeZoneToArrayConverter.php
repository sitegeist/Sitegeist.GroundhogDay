<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;

class DateTimeZoneToArrayConverter extends AbstractTypeConverter
{
    /**
     * @var array<int,string>
     */
    protected $sourceTypes = [\DateTimeZone::class];

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
     * @param \DateTimeZone $source
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
        return $source->getName();
    }
}
