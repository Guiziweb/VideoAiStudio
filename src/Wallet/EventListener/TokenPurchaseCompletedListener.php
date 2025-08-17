<?php

declare(strict_types=1);

namespace App\Wallet\EventListener;

use App\Entity\Customer\Customer;
use App\Wallet\Entity\WalletTransaction;
use App\Wallet\Enum\ProductCode;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
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

        // Vérifier si le paiement n'est pas complété pour éviter de traiter deux fois
        if ($payment->getState() !== 'completed') {
            return;
        }

        $order = $payment->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        // Vérifier si on a déjà traité cette commande (éviter les doublons)
        $existingTransaction = $this->entityManager
            ->getRepository(WalletTransaction::class)
            ->findOneBy(['reference' => 'Order #' . $order->getNumber()]);

        if ($existingTransaction !== null) {
            return; // Déjà traité
        }

        // Vérifier si la commande contient des tokens
        $totalTokens = $this->getTotalTokensInOrder($order);
        if ($totalTokens === 0) {
            return;
        }

        $customer = $order->getCustomer();
        if (!$customer instanceof Customer) {
            return;
        }

        // Le wallet doit déjà exister (créé à la création du customer)
        $wallet = $customer->getWallet();
        if (!$wallet) {
            throw new \RuntimeException('Customer wallet not found. This should not happen.');
        }

        // Créditer les tokens (crée automatiquement la transaction)
        $transaction = $wallet->credit($totalTokens, 'Order #' . $order->getNumber());

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
    }

    private function getTotalTokensInOrder(OrderInterface $order): int
    {
        $totalTokens = 0;

        foreach ($order->getItems() as $item) {
            $product = $item->getVariant()?->getProduct();

            if ($product && $product->getCode() === ProductCode::TOKEN_PACKS->value) {
                $variant = $item->getVariant();
                $channel = $order->getChannel();

                $channelPricing = $variant->getChannelPricingForChannel($channel);
                if ($channelPricing instanceof ChannelPricingInterface) {
                    $tokens = $channelPricing->getPrice(); // Le prix correspond au nombre de tokens
                    $totalTokens += $tokens * $item->getQuantity();
                }
            }
        }

        return $totalTokens;
    }
}
