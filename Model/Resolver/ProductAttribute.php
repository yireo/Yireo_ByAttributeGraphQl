<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model\Resolver;

use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Yireo\ByAttributeGraphQl\Model\AttributeValidator;
use Yireo\ByAttributeGraphQl\Model\CategoryValidator;

class ProductAttribute implements ResolverInterface
{
    public function __construct(
        private readonly AttributeValidator $attributeValidator,
        private readonly CategoryValidator $categoryValidator
    ) {
    }

    /**
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return array<string, mixed>
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!isset($args['code'])) {
            throw new GraphQlInputException(__('You should specify a product attribute code (like "color").'));
        }

        $store = $context->getExtensionAttributes()->getStore();
        if (!$store instanceof StoreInterface) {
            throw new GraphQlInputException(__('No store could be determined'));
        }

        $storeId = (int)$store->getId();
        $attribute = $this->attributeValidator->getValidAttribute($args['code']);
        $category = $this->categoryValidator->getValidCategory($args['category_id'] ?? null, $store);
        $categoryId = $category ? (int)$category->getId() : 0;

        return [
            'id' => $attribute->getAttributeId(),
            'code' => $attribute->getAttributeCode(),
            'label' => $attribute->getStoreLabel($storeId),
            'default_value' => (string)$attribute->getDefaultValue(),
            'category_id' => $categoryId,
            'options' => $this->getOptionsData($attribute, $storeId, $category),
        ];
    }

    /**
     * @param AbstractAttribute $attribute
     * @param int $storeId
     * @param Category|null $category
     * @return array<int, array<string, mixed>>
     */
    private function getOptionsData(AbstractAttribute $attribute, int $storeId, ?Category $category): array
    {
        $attribute->setStoreId($storeId);

        $optionsData = [];
        foreach ($attribute->getOptions() as $option) {
            $optionValue = (string)$option->getValue();
            if ($optionValue === '') {
                continue;
            }

            $optionsData[] = [
                'label' => (string)$option->getLabel(),
                'value' => $optionValue,
                'attribute' => $attribute,
                'store_id' => $storeId,
                'category' => $category,
            ];
        }

        return $optionsData;
    }
}
