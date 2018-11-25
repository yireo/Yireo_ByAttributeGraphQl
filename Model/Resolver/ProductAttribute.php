<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model\Resolver;

use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Config\Element\Field as ElementField;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Class ProductAttribute
 * @package Yireo\ByAttributeGraphQl\Model\Resolver
 */
class ProductAttribute implements ResolverInterface
{
    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * ProductAttribute constructor.
     * @param Config $eavConfig
     */
    public function __construct(
        Config $eavConfig
    ) {
        $this->eavConfig = $eavConfig;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param ElementField $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return mixed|Value
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($args['code'])) {
            throw new GraphQlInputException(__('You should specify a product attribute code (like "color").'));
        }

        $attributeCode = $args['code'];
        $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);

        if (!$attribute) {
            throw new GraphQlNoSuchEntityException(__('No such attribute could be found'));
        }

        $attributeData = [
            'id' => $attribute->getAttributeId(),
            'code' => $attribute->getAttributeCode(),
            'label' => $attribute->getDefaultFrontendLabel(),
            'default_value' => (string)$attribute->getDefaultValue(),
            'options' => $this->getOptionsDataByAttribute($attribute),
        ];

        return $attributeData;
    }

    private function getOptionsDataByAttribute(AttributeInterface $attribute)
    {
        $options = $attribute->getOptions();
        $optionsData = [];
        foreach ($options as $option) {
            if (!$option->getValue()) {
                continue;
            }

            $optionsData[] = [
                'label' => $option->getLabel(),
                'value' => $option->getValue(),
                'count' => 0,
            ];
        }

        return $optionsData;
    }
}