<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model\Resolver;

use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Yireo\ByAttributeGraphQl\Model\ProductCounter;

class ProductCount implements ResolverInterface
{
    public function __construct(
        private readonly ProductCounter $productCounter
    ) {
    }

    /**
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return int
     * @throws LocalizedException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $attribute = $value['attribute'] ?? null;
        if (!$attribute instanceof AbstractAttribute) {
            throw new LocalizedException(__('"attribute" value should be specified'));
        }

        $optionValue = (string)($value['value'] ?? '');
        if (!$this->hasOption($attribute, $optionValue)) {
            throw new GraphQlNoSuchEntityException(__('No such option could be found'));
        }

        $category = $value['category'] ?? null;

        return $this->productCounter->getProductCount(
            $attribute,
            $optionValue,
            (int)($value['store_id'] ?? 0),
            $category instanceof Category ? $category : null
        );
    }

    /**
     * @param AbstractAttribute $attribute
     * @param string $optionValue
     * @return bool
     */
    private function hasOption(AbstractAttribute $attribute, string $optionValue): bool
    {
        if ($optionValue === '') {
            return false;
        }

        foreach ($attribute->getOptions() as $option) {
            if ((string)$option->getValue() === $optionValue) {
                return true;
            }
        }

        return false;
    }
}
