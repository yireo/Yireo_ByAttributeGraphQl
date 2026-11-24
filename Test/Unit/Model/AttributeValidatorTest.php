<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Test\Unit\Model;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yireo\ByAttributeGraphQl\Model\AttributeValidator;

class AttributeValidatorTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function getCodes(): array
    {
        return [
            'simple' => ['color', true],
            'underscore' => ['shoe_size', true],
            'digits' => ['size2', true],
            'max length' => ['a' . str_repeat('b', 59), true],
            'too long' => ['a' . str_repeat('b', 60), false],
            'numeric' => ['1', false],
            'leading digit' => ['1color', false],
            'uppercase' => ['Color', false],
            'dash' => ['shoe-size', false],
            'space' => ['color ', false],
            'newline' => ["color\n", false],
            'quote' => ["color'", false],
            'empty' => ['', false],
            'integer' => [1, false],
            'null' => [null, false],
            'array' => [['color'], false],
        ];
    }

    #[DataProvider('getCodes')]
    public function testIsValidCode(mixed $code, bool $expected): void
    {
        $validator = new AttributeValidator($this->createMock(EavConfig::class));
        $this->assertSame($expected, $validator->isValidCode($code));
    }

    public function testInvalidCodeIsRejectedBeforeLookup(): void
    {
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects($this->never())->method('getAttribute');

        $this->expectException(GraphQlInputException::class);
        (new AttributeValidator($eavConfig))->getValidAttribute('1');
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function getAttributeData(): array
    {
        return [
            'filterable select' => [
                ['attribute_id' => 10, 'frontend_input' => 'select', 'is_filterable' => 1],
                true,
            ],
            'filterable in search multiselect' => [
                ['attribute_id' => 10, 'frontend_input' => 'multiselect', 'is_filterable_in_search' => 1],
                true,
            ],
            'not filterable' => [
                ['attribute_id' => 10, 'frontend_input' => 'select', 'is_filterable' => 0],
                false,
            ],
            'text input' => [
                ['attribute_id' => 10, 'frontend_input' => 'text', 'is_filterable' => 1],
                false,
            ],
            'missing attribute' => [
                ['frontend_input' => 'select', 'is_filterable' => 1],
                false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param bool $allowed
     */
    #[DataProvider('getAttributeData')]
    public function testGetValidAttribute(array $data, bool $allowed): void
    {
        $attribute = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $attribute->setData($data);

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($attribute);

        if (!$allowed) {
            $this->expectException(GraphQlNoSuchEntityException::class);
        }

        $this->assertSame($attribute, (new AttributeValidator($eavConfig))->getValidAttribute('color'));
    }
}
