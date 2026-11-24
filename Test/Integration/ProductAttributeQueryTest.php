<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Test\Integration;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\MultiselectAttribute as MultiselectAttributeFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Catalog\Test\Fixture\SelectAttribute as SelectAttributeFixture;
use Magento\Framework\GraphQl\Schema\Type\TypeRegistry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\GraphQl\Service\GraphQlRequest;
use Magento\Store\Test\Fixture\Website as WebsiteFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('graphql'), DbIsolation(true)]
class ProductAttributeQueryTest extends TestCase
{
    private const ATTRIBUTE_NOT_FOUND = 'No such attribute could be found';

    private const CATEGORY_NOT_FOUND = 'No such category could be found';

    #[
        DataFixture(SelectAttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_count',
            'is_filterable' => 1,
        ], 'attr'),
        DataFixture(CategoryFixture::class, as: 'category'),
        DataFixture(ProductFixture::class, [
            'category_ids' => ['$category.id$'],
            'custom_attributes' => ['tax_class_id' => '2', 'yireo_bagql_count' => '$attr.option_1$'],
        ]),
        DataFixture(ProductFixture::class, [
            'category_ids' => ['$category.id$'],
            'custom_attributes' => ['tax_class_id' => '2', 'yireo_bagql_count' => '$attr.option_1$'],
        ]),
        DataFixture(ProductFixture::class, [
            'category_ids' => ['$category.id$'],
            'custom_attributes' => ['tax_class_id' => '2', 'yireo_bagql_count' => '$attr.option_2$'],
        ]),
        DataFixture(ProductFixture::class, [
            'category_ids' => ['$category.id$'],
            'status' => Status::STATUS_DISABLED,
            'custom_attributes' => ['tax_class_id' => '2', 'yireo_bagql_count' => '$attr.option_1$'],
        ]),
        DataFixture(ProductFixture::class, [
            'category_ids' => ['$category.id$'],
            'visibility' => Visibility::VISIBILITY_NOT_VISIBLE,
            'custom_attributes' => ['tax_class_id' => '2', 'yireo_bagql_count' => '$attr.option_1$'],
        ]),
    ]
    public function testProductCountsOnlyIncludeEnabledAndVisibleProducts(): void
    {
        $categoryId = (int)$this->getFixture('category')->getId();

        $counts = $this->getCounts($this->query(
            '{ productAttribute(code: "yireo_bagql_count", category_id: ' . $categoryId . ') '
            . '{ code options { label value product_count } } }'
        ));
        $this->assertSame(['option_1' => 2, 'option_2' => 1], $counts);

        $counts = $this->getCounts($this->query(
            '{ productAttribute(code: "yireo_bagql_count") { options { label value product_count } } }'
        ));
        $this->assertSame(['option_1' => 2, 'option_2' => 1], $counts);
    }

    #[
        DataFixture(MultiselectAttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_multi',
            'is_filterable' => 1,
        ], 'attr'),
        DataFixture(CategoryFixture::class, as: 'category'),
        DataFixture(ProductFixture::class, [
            'category_ids' => ['$category.id$'],
            'custom_attributes' => [
                'tax_class_id' => '2',
                'yireo_bagql_multi' => ['$attr.option_1$', '$attr.option_2$'],
            ],
        ]),
        DataFixture(ProductFixture::class, [
            'category_ids' => ['$category.id$'],
            'custom_attributes' => ['tax_class_id' => '2', 'yireo_bagql_multi' => '$attr.option_2$'],
        ]),
    ]
    public function testMultiselectAttributeIsCountedPerOption(): void
    {
        $categoryId = (int)$this->getFixture('category')->getId();

        $counts = $this->getCounts($this->query(
            '{ productAttribute(code: "yireo_bagql_multi", category_id: ' . $categoryId . ') '
            . '{ options { label value product_count } } }'
        ));
        $this->assertSame(['option_1' => 1, 'option_2' => 2], $counts);
    }

    #[
        DataFixture(SelectAttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_website',
            'is_filterable' => 1,
        ], 'attr'),
        DataFixture(WebsiteFixture::class, as: 'website'),
        DataFixture(CategoryFixture::class, as: 'category'),
        DataFixture(ProductFixture::class, [
            'category_ids' => ['$category.id$'],
            'custom_attributes' => ['tax_class_id' => '2', 'yireo_bagql_website' => '$attr.option_1$'],
            'extension_attributes' => [
                'website_ids' => ['$website.id$'],
                'stock_item' => [
                    'use_config_manage_stock' => true,
                    'qty' => 100,
                    'is_qty_decimal' => false,
                    'is_in_stock' => true,
                ],
            ],
        ]),
    ]
    public function testProductsOfOtherWebsitesAreNotCounted(): void
    {
        $categoryId = (int)$this->getFixture('category')->getId();

        $counts = $this->getCounts($this->query(
            '{ productAttribute(code: "yireo_bagql_website", category_id: ' . $categoryId . ') '
            . '{ options { label value product_count } } }'
        ));
        $this->assertSame(['option_1' => 0, 'option_2' => 0], $counts);
    }

    #[
        DataFixture(SelectAttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_empty',
            'is_filterable' => 1,
        ], 'attr'),
        DataFixture(CategoryFixture::class, as: 'category'),
    ]
    public function testEmptyCategoryReturnsZeroCounts(): void
    {
        $categoryId = (int)$this->getFixture('category')->getId();

        $result = $this->query(
            '{ productAttribute(code: "yireo_bagql_empty", category_id: ' . $categoryId . ') '
            . '{ options { label value product_count } } }'
        );
        $this->assertArrayNotHasKey('errors', $result, (string)json_encode($result));
        $this->assertSame(['option_1' => 0, 'option_2' => 0], $this->getCounts($result));
    }

    #[
        DataFixture(SelectAttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_hidden',
            'is_filterable' => 0,
            'is_filterable_in_search' => 0,
        ], 'attr'),
    ]
    public function testNonFilterableAttributeIsNotFound(): void
    {
        $result = $this->query('{ productAttribute(code: "yireo_bagql_hidden") { id options { value } } }');
        $this->assertErrorMessage(self::ATTRIBUTE_NOT_FOUND, $result);
    }

    #[
        DataFixture(AttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_text',
            'is_filterable' => 1,
        ], 'attr'),
    ]
    public function testNonOptionAttributeIsNotFound(): void
    {
        $result = $this->query('{ productAttribute(code: "yireo_bagql_text") { id } }');
        $this->assertErrorMessage(self::ATTRIBUTE_NOT_FOUND, $result);
    }

    public function testUnknownAttributeIsNotFound(): void
    {
        $result = $this->query('{ productAttribute(code: "yireo_bagql_unknown") { id } }');
        $this->assertErrorMessage(self::ATTRIBUTE_NOT_FOUND, $result);
    }

    public function testNumericAttributeCodeIsRejected(): void
    {
        $result = $this->query('{ productAttribute(code: "1") { id code } }');
        $this->assertErrorMessage('Invalid product attribute code.', $result);
    }

    #[
        DataFixture(SelectAttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_inactive',
            'is_filterable' => 1,
        ], 'attr'),
        DataFixture(CategoryFixture::class, ['is_active' => false], 'category'),
    ]
    public function testInactiveCategoryIsNotFound(): void
    {
        $categoryId = (int)$this->getFixture('category')->getId();

        $result = $this->query(
            '{ productAttribute(code: "yireo_bagql_inactive", category_id: ' . $categoryId . ') { id } }'
        );
        $this->assertErrorMessage(self::CATEGORY_NOT_FOUND, $result);
    }

    #[
        DataFixture(SelectAttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_foreign',
            'is_filterable' => 1,
        ], 'attr'),
        DataFixture(CategoryFixture::class, ['parent_id' => 1], 'root'),
        DataFixture(CategoryFixture::class, ['parent_id' => '$root.id$'], 'category'),
    ]
    public function testCategoryOfOtherRootIsNotFound(): void
    {
        $categoryId = (int)$this->getFixture('category')->getId();

        $result = $this->query(
            '{ productAttribute(code: "yireo_bagql_foreign", category_id: ' . $categoryId . ') { id } }'
        );
        $this->assertErrorMessage(self::CATEGORY_NOT_FOUND, $result);
    }

    #[
        DataFixture(SelectAttributeFixture::class, [
            'attribute_code' => 'yireo_bagql_invalid',
            'is_filterable' => 1,
        ], 'attr'),
    ]
    public function testInvalidCategoryIdIsRejected(): void
    {
        $result = $this->query('{ productAttribute(code: "yireo_bagql_invalid", category_id: 0) { id } }');
        $this->assertErrorMessage('Invalid category ID.', $result);

        $result = $this->query('{ productAttribute(code: "yireo_bagql_invalid", category_id: 999999) { id } }');
        $this->assertErrorMessage(self::CATEGORY_NOT_FOUND, $result);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, int>
     */
    private function getCounts(array $result): array
    {
        $this->assertArrayNotHasKey('errors', $result, (string)json_encode($result));

        $counts = [];
        foreach ($result['data']['productAttribute']['options'] ?? [] as $option) {
            $counts[$option['label']] = $option['product_count'];
        }

        ksort($counts);
        return $counts;
    }

    /**
     * @param string $expectedMessage
     * @param array<string, mixed> $result
     * @return void
     */
    private function assertErrorMessage(string $expectedMessage, array $result): void
    {
        $this->assertArrayHasKey('errors', $result, (string)json_encode($result));
        $this->assertSame($expectedMessage, $result['errors'][0]['message'] ?? '', (string)json_encode($result));
    }

    /**
     * @param string $query
     * @return array<string, mixed>
     */
    private function query(string $query): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(TypeRegistry::class)->_resetState();
        // @phpstan-ignore class.notFound
        $response = $objectManager->create(GraphQlRequest::class)->send($query);

        return $objectManager->get(SerializerInterface::class)->unserialize($response->getContent());
    }

    /**
     * @param string $name
     * @return mixed
     */
    private function getFixture(string $name): mixed
    {
        return DataFixtureStorageManager::getStorage()->get($name);
    }
}
