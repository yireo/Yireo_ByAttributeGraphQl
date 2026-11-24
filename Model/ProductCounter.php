<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\App\Cache\Type\Collection as CollectionCache;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Api\Data\StoreInterface;

class ProductCounter
{
    private const CACHE_PREFIX = 'yireo_byattribute_graphql_counts';

    private const CACHE_LIFETIME = 3600;

    /**
     * @var array<string, array<string, int>>
     */
    private array $counts = [];

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly Visibility $visibility,
        private readonly StockHelper $stockHelper,
        private readonly CollectionCache $cache,
        private readonly CacheState $cacheState,
        private readonly SerializerInterface $serializer,
        private readonly CacheTags $cacheTags
    ) {
    }

    /**
     * @param AbstractAttribute $attribute
     * @param string $optionValue
     * @param StoreInterface $store
     * @param Category|null $category
     * @return int
     */
    public function getProductCount(
        AbstractAttribute $attribute,
        string $optionValue,
        StoreInterface $store,
        ?Category $category = null
    ): int {
        $counts = $this->getCounts($attribute, $store, $category);
        return $counts[$optionValue] ?? 0;
    }

    /**
     * @param AbstractAttribute $attribute
     * @param StoreInterface $store
     * @param Category|null $category
     * @return array<string, int>
     */
    public function getCounts(AbstractAttribute $attribute, StoreInterface $store, ?Category $category = null): array
    {
        $storeId = (int)$store->getId();
        $categoryId = $category ? (int)$category->getId() : 0;
        $cacheKey = implode('_', [self::CACHE_PREFIX, (int)$attribute->getAttributeId(), $storeId, $categoryId]);
        if (isset($this->counts[$cacheKey])) {
            return $this->counts[$cacheKey];
        }

        $counts = $this->loadFromCache($cacheKey);
        if ($counts === null) {
            $counts = $this->loadCounts($attribute, $store, $category);
            $this->saveToCache($cacheKey, $counts, $categoryId);
        }

        $this->counts[$cacheKey] = $counts;
        return $counts;
    }

    /**
     * @param AbstractAttribute $attribute
     * @param StoreInterface $store
     * @param Category|null $category
     * @return array<string, int>
     */
    private function loadCounts(AbstractAttribute $attribute, StoreInterface $store, ?Category $category): array
    {
        $storeId = (int)$store->getId();
        $collection = $this->getProductCollection($store, $category);
        $select = clone $collection->getSelect();
        $select->reset(Select::COLUMNS)
            ->reset(Select::ORDER)
            ->reset(Select::LIMIT_COUNT)
            ->reset(Select::LIMIT_OFFSET)
            ->join(
                ['attribute_index' => $collection->getTable('catalog_product_index_eav')],
                'attribute_index.entity_id = e.entity_id',
                []
            )
            ->where('attribute_index.attribute_id = ?', (int)$attribute->getAttributeId())
            ->where('attribute_index.store_id = ?', $storeId)
            ->columns([
                'value' => 'attribute_index.value',
                'count' => new Expression('COUNT(DISTINCT e.entity_id)'),
            ])
            ->group('attribute_index.value');

        $counts = [];
        foreach ($collection->getConnection()->fetchPairs($select) as $value => $count) {
            $counts[(string)$value] = (int)$count;
        }

        return $counts;
    }

    /**
     * @param StoreInterface $store
     * @param Category|null $category
     * @return ProductCollection
     */
    private function getProductCollection(StoreInterface $store, ?Category $category): ProductCollection
    {
        $storeId = (int)$store->getId();
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addWebsiteFilter([(int)$store->getWebsiteId()]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->visibility->getVisibleInSiteIds());

        if ($category instanceof Category) {
            $collection->addCategoryFilter($category);
        }

        $this->stockHelper->addIsInStockFilterToCollection($collection);

        return $collection;
    }

    /**
     * @param string $cacheKey
     * @return array<string, int>|null
     */
    private function loadFromCache(string $cacheKey): ?array
    {
        if (!$this->cacheState->isEnabled(CollectionCache::TYPE_IDENTIFIER)) {
            return null;
        }

        $data = $this->cache->load($cacheKey);
        if (!is_string($data) || $data === '') {
            return null;
        }

        $counts = $this->serializer->unserialize($data);
        if (!is_array($counts)) {
            return null;
        }

        $sanitizedCounts = [];
        foreach ($counts as $value => $count) {
            $sanitizedCounts[(string)$value] = (int)$count;
        }

        return $sanitizedCounts;
    }

    /**
     * @param string $cacheKey
     * @param array<string, int> $counts
     * @param int $categoryId
     * @return void
     */
    private function saveToCache(string $cacheKey, array $counts, int $categoryId): void
    {
        if (!$this->cacheState->isEnabled(CollectionCache::TYPE_IDENTIFIER)) {
            return;
        }

        $this->cache->save(
            (string)$this->serializer->serialize($counts),
            $cacheKey,
            $this->cacheTags->getTags($categoryId),
            self::CACHE_LIFETIME
        );
    }
}
