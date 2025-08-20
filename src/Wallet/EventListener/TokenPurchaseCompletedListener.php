<?php

declare(strict_types=1);

namespace App\Wallet\EventListener;

use App\Shared\Entity\Customer\Customer;
use App\Shared\Entity\Product\ProductVariant;
use App\Wallet\Enum\ProductCode;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

#[AsEventListener(event: 'workflow.sylius_payment.completed.complete', method: 'onPaymentWorkflowComplete')]
final readonly class TokenPurchaseCompletedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onPaymentWorkflowComplete(CompletedEvent $event): void
    {
        $payment = $event->getSubject();
        $this->processTokenPurchase($payment);
    }

    private function processTokenPurchase(mixed $payment): void
    {
        if (!$payment instanceof PaymentInterface) {
            return;
        }

        if ($payment->getState() !== PaymentInterface::STATE_COMPLETED) {
            return;
        }

        $order = $payment->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $totalTokens = $this->getTotalTokensInOrder($order);
        if ($totalTokens === 0) {
            return;
        }

        $customer = $order->getCustomer();
        if (!$customer instanceof Customer) {
            return;
        }
        $wallet = $customer->getWallet();

        $wallet->credit($totalTokens);

        $this->entityManager->persist($wallet);
        $this->entityManager->flush();
    }

    private function getTotalTokensInOrder(OrderInterface $order): int
    {
        $totalTokens = 0;

        foreach ($order->getItems() as $item) {
            $product = $item->getVariant()?->getProduct();

            if ($product && $product->getCode() === ProductCode::TOKEN_PACKS->value) {
                $variant = $item->getVariant();

                if ($variant instanceof ProductVariant && $variant->getTokenAmount() !== null) {
                    $tokens = $variant->getTokenAmount();
                    $totalTokens += $tokens * $item->getQuantity();
                }
            }
        }

        return $totalTokens;
    }
}
