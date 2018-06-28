<?php

declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model\Resolver\Attribute;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Bundle\Model\Product\Type;
use Magento\BundleGraphQl\Model\Resolver\Options\Collection;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\Value as ResolverValue;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory as ResolverValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;

/**
 * {@inheritdoc}
 */
class Value implements ResolverInterface
{
    /**
     * @var Collection
     */
    private $bundleOptionCollection;

    /**
     * @var ResolverValueFactory
     */
    private $valueFactory;

    /**
     * @var MetadataPool
     */
    private $metdataPool;

    /**
     * @param Collection $bundleOptionCollection
     * @param ResolverValueFactory $valueFactory
     * @param MetadataPool $metdataPool
     */
    public function __construct(
        Collection $bundleOptionCollection,
        ResolverValueFactory $valueFactory,
        MetadataPool $metdataPool
    ) {
        $this->bundleOptionCollection = $bundleOptionCollection;
        $this->valueFactory = $valueFactory;
        $this->metdataPool = $metdataPool;
    }

    /**
     * Fetch and format bundle option items.
     *
     * {@inheritDoc}
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null) : ResolverValue
    {
        $linkField = $this->metdataPool->getMetadata(ProductInterface::class)->getLinkField();
        $this->bundleOptionCollection->addParentFilterData(
            (int)$value[$linkField],
            (int)$value['entity_id'],
            $value[ProductInterface::SKU]
        );
        $result = function () use ($value, $linkField) {
            return $this->bundleOptionCollection->getOptionsByParentId((int)$value[$linkField]);
        };
        return $this->valueFactory->create($result);
    }
}