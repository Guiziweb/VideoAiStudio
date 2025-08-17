<?php

declare(strict_types=1);

namespace App\Wallet\Component;

use App\Wallet\Enum\ProductCode;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('app:token_packs')]
final class TokenPacksComponent
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    public function getTokenProduct(): ?ProductInterface
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return null;
        }

        /** @var ProductInterface|null $tokenProduct */
        $tokenProduct = $this->productRepository->findOneByChannelAndCode($channel, ProductCode::TOKEN_PACKS->value);

        return $tokenProduct;
    }
}
