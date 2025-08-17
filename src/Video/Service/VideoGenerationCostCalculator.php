<?php

declare(strict_types=1);

namespace App\Video\Service;

use App\Video\Enum\ProductCode;
use Sylius\Component\Core\Context\ShopperContextInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;

final readonly class VideoGenerationCostCalculator
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ShopperContextInterface $shopperContext,
    ) {
    }

    public function getGenerationCost(): int
    {
        $product = $this->productRepository->findOneByCode(ProductCode::VIDEO_GENERATION->value);
        if (!$product || !$product->isEnabled()) {
            throw new \RuntimeException('Video generation product not found or disabled');
        }

        $variant = $product->getVariants()->first();
        if (!$variant instanceof ProductVariantInterface) {
            throw new \RuntimeException('Video generation product has no variant');
        }

        $channel = $this->shopperContext->getChannel();

        $price = $variant->getChannelPricingForChannel($channel)?->getPrice();
        if ($price === null) {
            throw new \RuntimeException(sprintf('No price configured for video generation on channel "%s"', $channel->getCode()));
        }

        return $price;
    }
}
