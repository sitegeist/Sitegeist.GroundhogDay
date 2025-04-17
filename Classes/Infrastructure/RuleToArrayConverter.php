<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Recurr\Rule;

class RuleToArrayConverter extends AbstractTypeConverter
{
    /**
     * @var array<int,string>
     */
    protected $sourceTypes = [Rule::class];

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
     * @param Rule $source
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
        return $source->getString();
    }
}
