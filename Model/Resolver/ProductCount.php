<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model\Resolver;

use Exception;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Config\Element\Field as ElementField;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Yireo\ByAttributeGraphQl\Model\ProductCounter;
use Zend_Db_Select_Exception;

/**
 * Class ProductCount
 * @package Yireo\ByAttributeGraphQl\Model\Resolver
 */
class ProductCount implements ResolverInterface
{
    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * @var ProductCounter
     */
    private $productCounter;

    /**
     * ProductAttribute constructor.
     * @param Config $eavConfig
     * @param ProductCounter $productCounter
     */
    public function __construct(
        Config $eavConfig,
        ProductCounter $productCounter
    ) {
        $this->eavConfig = $eavConfig;
        $this->productCounter = $productCounter;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param ElementField $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed|Value
     * @throws Exception
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['attribute_code'])) {
            throw new GraphQlInputException(__('You should specify a product attribute code (like "color").'));
        }

        $attributeCode = $value['attribute_code'];
        $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);

        if (!$attribute) {
            throw new GraphQlNoSuchEntityException(__('No such attribute could be found'));
        }

        $categoryId = 0;
        if (isset($value['category_id'])) {
            $categoryId = (int)$value['category_id'];
        }

        $options = $attribute->getOptions();
        $option = false;
        foreach ($options as $option) {
            if ($option->getValue() === $value['value']) {
                break;
            }
        }

        if ($option === false) {
            throw new GraphQlNoSuchEntityException(__('No such option could be found'));
        }

        $productCounter = $this->productCounter->setCategoryId($categoryId);
        $productCount = $productCounter->getProductCountByAttributeValue($attribute, $option);

        return $productCount;
    }
}
