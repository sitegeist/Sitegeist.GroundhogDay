<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Sitegeist\GroundhogDay\Domain\EventOccurrenceSpecification;

class ArrayToDateTimeZoneConverter extends AbstractTypeConverter
{
    /**
     * @var array<int,string>
     */
    protected $sourceTypes = ['array'];

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
     * @param array{timezone?:string} $source
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
            if (array_key_exists('timezone', $source)) {
                return new \DateTimeZone($source[ 'timezone' ]);
            } else {
                return null;
            }
        } catch (\Exception) {
            return null;
        }
    }
}
