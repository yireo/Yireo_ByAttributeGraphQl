<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;

class AttributeValidator
{
    private const CODE_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

    private const ALLOWED_FRONTEND_INPUTS = ['select', 'multiselect'];

    public function __construct(
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @param mixed $attributeCode
     * @return AbstractAttribute
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    public function getValidAttribute(mixed $attributeCode): AbstractAttribute
    {
        if (!$this->isValidCode($attributeCode)) {
            throw new GraphQlInputException(__('Invalid product attribute code.'));
        }

        try {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
        } catch (LocalizedException) {
            $attribute = false;
        }

        if (!$attribute instanceof AbstractAttribute
            || !$attribute->getAttributeId()
            || !$this->isAllowedAttribute($attribute)) {
            throw new GraphQlNoSuchEntityException(__('No such attribute could be found'));
        }

        return $attribute;
    }

    /**
     * @param mixed $attributeCode
     * @return bool
     */
    public function isValidCode(mixed $attributeCode): bool
    {
        return is_string($attributeCode) && preg_match(self::CODE_PATTERN, $attributeCode) === 1;
    }

    /**
     * @param AbstractAttribute $attribute
     * @return bool
     */
    private function isAllowedAttribute(AbstractAttribute $attribute): bool
    {
        if (!in_array($attribute->getFrontendInput(), self::ALLOWED_FRONTEND_INPUTS, true)) {
            return false;
        }

        return (bool)$attribute->getData('is_filterable') || (bool)$attribute->getData('is_filterable_in_search');
    }
}
