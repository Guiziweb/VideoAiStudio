<?php

declare(strict_types=1);

namespace App\Wallet\Payment\CommandHandler;

use App\Shared\Entity\Customer\Customer;
use App\Wallet\Payment\Command\PayWalletCommand;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final readonly class PayWalletCommandHandler
{
    public function __construct(
        /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface> */
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private EntityManagerInterface $entityManager,
        private WorkflowInterface $paymentRequestStateMachine,
        private WorkflowInterface $paymentStateMachine,
        private WorkflowInterface $orderCheckoutStateMachine,
        private WorkflowInterface $orderStateMachine,
    ) {
    }

    public function __invoke(PayWalletCommand $command): void
    {
        $paymentRequest = $this->paymentRequestRepository->find($command->getPaymentRequestId());

        if (!$paymentRequest instanceof PaymentRequestInterface) {
            throw new \InvalidArgumentException('Payment request not found');
        }

        $payment = $paymentRequest->getPayment();

        if (!$payment instanceof PaymentInterface) {
            throw new \InvalidArgumentException('Payment not found');
        }

        if ($payment->getState() === BasePaymentInterface::STATE_COMPLETED) {
            return;
        }

        $order = $payment->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new \InvalidArgumentException('Order not found');
        }

        $customer = $order->getCustomer();
        if (!$customer instanceof Customer) {
            throw new \InvalidArgumentException('Customer not found');
        }

        $wallet = $customer->getWallet();

        $amount = $payment->getAmount();
        if ($amount === null) {
            throw new \InvalidArgumentException('Payment amount cannot be null');
        }

        if ($this->paymentStateMachine->can($payment, PaymentTransitions::TRANSITION_CREATE)) {
            $this->paymentStateMachine->apply($payment, PaymentTransitions::TRANSITION_CREATE);
        }

        if (!$wallet->canAfford($amount)) {
            // CAS D'ÉCHEC - Fonds insuffisants
            $payment->setDetails(['error' => 'Insufficient funds']);

            if ($this->paymentStateMachine->can($payment, PaymentTransitions::TRANSITION_FAIL)) {
                $this->paymentStateMachine->apply($payment, PaymentTransitions::TRANSITION_FAIL);
            }

            // Marquer la request comme failed
            if ($this->paymentRequestStateMachine->can($paymentRequest, PaymentRequestTransitions::TRANSITION_FAIL)) {
                $this->paymentRequestStateMachine->apply($paymentRequest, PaymentRequestTransitions::TRANSITION_FAIL);
            }

            // En cas d'échec, on ne complète pas le checkout
            // On peut optionnellement annuler la commande si elle n'est plus en cart

            if ($this->orderStateMachine->can($order, OrderTransitions::TRANSITION_CANCEL)) {
                $this->orderStateMachine->apply($order, OrderTransitions::TRANSITION_CANCEL);
            }
        } else {
            // CAS DE SUCCÈS - Débiter le wallet
            $wallet->debit($amount);
            $this->entityManager->persist($wallet);
            $this->entityManager->flush();

            if ($this->paymentStateMachine->can($payment, PaymentTransitions::TRANSITION_COMPLETE)) {
                $this->paymentStateMachine->apply($payment, PaymentTransitions::TRANSITION_COMPLETE);
            }

            // Marquer la request comme completed
            if ($this->paymentRequestStateMachine->can($paymentRequest, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
                $this->paymentRequestStateMachine->apply($paymentRequest, PaymentRequestTransitions::TRANSITION_COMPLETE);
            }

            // Compléter le checkout de la commande pour déclencher la soumission de la vidéo
            $this->completeCheckoutSteps($order);
        }
    }

    private function completeCheckoutSteps(OrderInterface $order): void
    {
        // 1. Address (cart -> addressed)
        if ($this->orderCheckoutStateMachine->can($order, OrderCheckoutTransitions::TRANSITION_ADDRESS)) {
            $this->orderCheckoutStateMachine->apply($order, OrderCheckoutTransitions::TRANSITION_ADDRESS);
        }

        // 2. Skip shipping (addressed -> shipping_skipped) - produit digital
        if ($this->orderCheckoutStateMachine->can($order, OrderCheckoutTransitions::TRANSITION_SKIP_SHIPPING)) {
            $this->orderCheckoutStateMachine->apply($order, OrderCheckoutTransitions::TRANSITION_SKIP_SHIPPING);
        }

        // 3. Skip payment (shipping_skipped -> payment_skipped) - déjà payé par wallet
        if ($this->orderCheckoutStateMachine->can($order, OrderCheckoutTransitions::TRANSITION_SKIP_PAYMENT)) {
            $this->orderCheckoutStateMachine->apply($order, OrderCheckoutTransitions::TRANSITION_SKIP_PAYMENT);
        }

        // 4. Complete (payment_skipped -> completed)
        if ($this->orderCheckoutStateMachine->can($order, OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
            $this->orderCheckoutStateMachine->apply($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        }
    }
}
