<?php

declare(strict_types=1);

namespace App\Wallet\EventListener;

use App\Wallet\Enum\ProductCode;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

#[AsEventListener(event: 'sylius.cart_item_add')]
final readonly class CartClearListener
{
    public function __invoke(GenericEvent $event): void
    {
        $addToCartCommand = $event->getSubject();

        if (!$addToCartCommand instanceof AddToCartCommandInterface) {
            return;
        }

        $cart = $addToCartCommand->getCart();

        $orderItem = $addToCartCommand->getCartItem();

        $productVariant = $orderItem->getVariant();
        if (!$productVariant) {
            return;
        }

        $product = $productVariant->getProduct();
        if (!$product instanceof ProductInterface) {
            return;
        }

        if ($product->getCode() === ProductCode::TOKEN_PACKS->value) {
            foreach ($cart->getItems() as $existingItem) {
                $cart->removeItem($existingItem);
            }
        }
    }
}
