<?php
declare(strict_types=1);

namespace Yireo\ByAttributeGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;
use Yireo\ByAttributeGraphQl\Model\CacheTags;

class Identity implements IdentityInterface
{
    public function __construct(
        private readonly CacheTags $cacheTags
    ) {
    }

    /**
     * @param array<string, mixed> $resolvedData
     * @return string[]
     */
    public function getIdentities(array $resolvedData): array
    {
        return $this->cacheTags->getTags((int)($resolvedData['category_id'] ?? 0));
    }
}
