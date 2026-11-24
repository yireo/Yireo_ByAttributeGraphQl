<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class CategoryValidator
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param mixed $categoryId
     * @param StoreInterface $store
     * @return Category|null
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    public function getValidCategory(mixed $categoryId, StoreInterface $store): ?Category
    {
        if ($categoryId === null) {
            return null;
        }

        if (!is_int($categoryId) || $categoryId <= 0) {
            throw new GraphQlInputException(__('Invalid category ID.'));
        }

        try {
            $category = $this->categoryRepository->get($categoryId, (int)$store->getId());
        } catch (NoSuchEntityException) {
            throw $this->getNotFoundException();
        }

        if (!$category instanceof Category
            || !$category->getIsActive()
            || !$this->isCategoryInStore($category, $store)) {
            throw $this->getNotFoundException();
        }

        return $category;
    }

    /**
     * @param Category $category
     * @param StoreInterface $store
     * @return bool
     */
    private function isCategoryInStore(Category $category, StoreInterface $store): bool
    {
        try {
            $rootCategoryId = (string)$this->storeManager->getGroup($store->getStoreGroupId())->getRootCategoryId();
        } catch (\Exception) {
            return false;
        }

        $pathIds = explode('/', (string)$category->getPath());
        return in_array($rootCategoryId, $pathIds, true);
    }

    /**
     * @return GraphQlNoSuchEntityException
     */
    private function getNotFoundException(): GraphQlNoSuchEntityException
    {
        return new GraphQlNoSuchEntityException(__('No such category could be found'));
    }
}
