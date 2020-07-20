<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model;

use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

use Laminas\Db\Sql\Select;

/**
 * Class ProductCounter
 * @package Yireo\ByAttributeGraphQl\Model
 */
class ProductCounter
{
    /**
     * @var OptionCollectionFactory
     */
    protected $optionCollectionFactory;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var int
     */
    private $categoryId = 0;

    /**
     * @var array[]
     */
    private $productCountRows = [];

    /**
     * @var int[]
     */
    private $productIds = [];

    /**
     * ProductCounter constructor.
     * @param OptionCollectionFactory $optionCollectionFactory
     * @param EavConfig $eavConfig
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        OptionCollectionFactory $optionCollectionFactory,
        EavConfig $eavConfig,
        ResourceConnection $resourceConnection
    ) {
        $this->eavConfig = $eavConfig;
        $this->optionCollectionFactory = $optionCollectionFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param int $categoryId
     * @return $this
     */
    public function setCategoryId(int $categoryId)
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    /**
     * @param AttributeInterface $attribute
     * @param AttributeOptionInterface $attributeOption
     * @return int
     * @throws LocalizedException
     */
    public function getProductCountByAttributeValue(
        AttributeInterface $attribute,
        AttributeOptionInterface $attributeOption
    ) {
        $productIds = $this->getProductIds();
        if (empty($productIds)) {
            return 0;
        }

        $rows = $this->getProductCountForAttributeOptions($productIds);
        foreach ($rows as $row) {
            if ($row['option_id'] == $attributeOption->getValue()) {
                return $row['count'];
            }
        }

        return 0;
    }

    /**
     * @return int
     */
    private function getCategoryId(): int
    {
        return $this->categoryId;
    }

    /**
     * @param array $productIds
     * @return array
     */
    private function getProductCountForAttributeOptions(array $productIds)
    {
        $hash = implode(',', $productIds);
        if (isset($this->productCountRows[$hash])) {
            return $this->productCountRows[$hash];
        }

        $mainTable = $this->resourceConnection->getTableName('eav_attribute_option');
        $optionValueTable = $this->resourceConnection->getTableName('eav_attribute_option_value');
        $productValueTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');

        $select = $this->getSelect();
        $select
            ->from(['main_table' => $mainTable])
            ->reset(Select::COLUMNS)
            ->columns([
                'option_id' => 'main_table.option_id',
                'value' => 'option_value.value',
                'count' => 'COUNT(product_value.entity_id)'
            ])
            ->join(
                ['option_value' => $optionValueTable],
                'option_value.option_id = main_table.option_id',
                ''
            )
            ->join(
                ['product_value' => $productValueTable],
                'FIND_IN_SET(main_table.option_id, product_value.value)',
                ''
            )
            ->where('main_table.attribute_id=140')
            ->where('product_value.entity_id IN (' . implode(',', $productIds) . ')')
            ->group(['main_table.option_id', 'option_value.value']);

        $statement = $this->resourceConnection->getConnection()->query($select);
        $this->productCountRows[$hash] = $statement->fetchAll();
        return $this->productCountRows[$hash];
    }

    /**
     * @return mixed
     * @throws LocalizedException
     */
    private function getProductIds()
    {
        $categoryId = $this->getCategoryId();
        if (isset($this->productIds[$categoryId])) {
            return $this->productIds[$categoryId];
        }

        $visibilityAttribute = $this->eavConfig->getAttribute('catalog_product', 'visibility');
        $mainTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $categoryProductTable = $this->resourceConnection->getTableName('catalog_category_product');
        $productIntTable = $this->resourceConnection->getTableName('catalog_product_entity_int');

        $select = $this->getSelect();
        $select
            ->from(['main_table' => $mainTable])
            ->reset(Select::COLUMNS)
            ->columns(['id' => 'main_table.entity_id'])
            ->join(
                ['category_product' => $categoryProductTable],
                'category_product.product_id=main_table.entity_id',
                ''
            )
            ->join(
                ['product_value' => $productIntTable],
                'main_table.entity_id=product_value.entity_id',
                ''
            )
            ->where('product_value.attribute_id=' . $visibilityAttribute->getAttributeId())
            ->where('product_value.value IN (2,3,4)');

        if ($categoryId > 0) {
            $select->where('category_product.category_id=' . $categoryId);
        }

        $statement = $this->resourceConnection->getConnection()->query($select);
        $rows = $statement->fetchAll();

        foreach ($rows as $row) {
            $this->productIds[$categoryId][] = $row['id'];
        }

        return $this->productIds[$categoryId];
    }

    /**
     * @return Select
     */
    private function getSelect(): Select
    {
        return new Select($this->resourceConnection->getConnection());
    }
}
