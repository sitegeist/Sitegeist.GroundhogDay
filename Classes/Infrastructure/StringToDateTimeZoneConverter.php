<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;

class StringToDateTimeZoneConverter extends AbstractTypeConverter
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
    protected $targetType = \DateTimeZone::class;

    /**
     * @var integer
     */
    protected $priority = 1;

    /**
     * @param string $source
     * @param string $targetType,
     * @param array<mixed> $convertedChildProperties
     * @return ?\DateTimeZone
     */
    public function convertFrom(
        $source,
        $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ) {
        try {
            return new \DateTimeZone($source);
        } catch (\Exception) {
            return null;
        }
    }
}
