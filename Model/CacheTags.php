<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute;

class CacheTags
{
    /**
     * @param int $categoryId
     * @return string[]
     */
    public function getTags(int $categoryId = 0): array
    {
        $tags = [Product::CACHE_TAG, Attribute::CACHE_TAG];
        if ($categoryId > 0) {
            $tags[] = Product::CACHE_PRODUCT_CATEGORY_TAG . '_' . $categoryId;
        }

        return $tags;
    }
}
