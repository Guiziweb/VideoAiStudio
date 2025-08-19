<?php

declare(strict_types=1);

namespace App\Shared\PaymentMethod\Resolver;

use App\Video\Enum\ProductCode as VideoProductCode;
use App\Wallet\Enum\PaymentMethodCode;
use App\Wallet\Enum\ProductCode as WalletProductCode;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator('sylius.resolver.payment_methods')]
final readonly class ProductTypePaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    public function __construct(
        #[AutowireDecorated]
        private PaymentMethodsResolverInterface $decoratedPaymentMethodsResolver,
    ) {
    }

    public function getSupportedMethods(BasePaymentInterface $subject): array
    {
        $methods = $this->decoratedPaymentMethodsResolver->getSupportedMethods($subject);

        if (!$subject instanceof PaymentInterface) {
            return $methods;
        }

        $order = $subject->getOrder();
        if (!$order instanceof OrderInterface) {
            return $methods;
        }

        $hasTokenPacks = $this->hasTokenPacksInOrder($order);
        $hasVideoGenerationProducts = $this->hasVideoGenerationProductsInOrder($order);

        return array_filter($methods, function ($method) use ($hasTokenPacks, $hasVideoGenerationProducts) {
            // Si le panier contient des token packs, autoriser toutes les méthodes sauf wallet
            if ($hasTokenPacks) {
                return $method->getCode() !== PaymentMethodCode::WALLET->value;
            }

            // Si le panier contient des produits de génération vidéo, autoriser uniquement wallet
            if ($hasVideoGenerationProducts) {
                return $method->getCode() === PaymentMethodCode::WALLET->value;
            }

            // Par défaut, toutes les méthodes sont disponibles (panier vide ou autres produits)
            return true;
        });
    }

    public function supports(BasePaymentInterface $subject): bool
    {
        return $this->decoratedPaymentMethodsResolver->supports($subject);
    }

    private function hasTokenPacksInOrder(OrderInterface $order): bool
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getVariant()?->getProduct();
            if ($product && $product->getCode() === WalletProductCode::TOKEN_PACKS->value) {
                return true;
            }
        }

        return false;
    }

    private function hasVideoGenerationProductsInOrder(OrderInterface $order): bool
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getVariant()?->getProduct();
            if ($product && $product->getCode() === VideoProductCode::VIDEO_GENERATION->value) {
                return true;
            }
        }

        return false;
    }
}
