<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Recurr\Rule;

class StringToRuleConverter extends AbstractTypeConverter
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
    protected $targetType = Rule::class;

    /**
     * @var integer
     */
    protected $priority = 1;

    /**
     * @param string $source
     * @param string $targetType,
     * @param array<mixed> $convertedChildProperties
     * @return Rule
     */
    public function convertFrom(
        $source,
        $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ) {
        return new Rule($source);
    }
}
