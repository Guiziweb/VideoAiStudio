<?php

declare(strict_types=1);

namespace App\Wallet\Payment\CommandHandler;

use App\Entity\Customer\Customer;
use App\Wallet\Entity\Wallet;
use App\Wallet\Payment\Command\PayWalletCommand;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
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

        // Vérifier si le paiement est déjà complété
        if ($payment->getState() === BasePaymentInterface::STATE_COMPLETED) {
            return; // Déjà traité, ne rien faire
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
        if (!$wallet instanceof Wallet) {
            throw new \InvalidArgumentException('Customer must have a wallet');
        }

        $amount = $payment->getAmount();
        if ($amount === null) {
            throw new \InvalidArgumentException('Payment amount cannot be null');
        }

        if (!$wallet->canAfford($amount)) {
            // Stocker la raison de l'échec dans les détails du paiement
            $payment->setDetails([
                'message' => 'app.ui.wallet_insufficient_funds',
            ]);

            // Marquer le payment comme failed
            if ($this->paymentStateMachine->can($payment, PaymentTransitions::TRANSITION_FAIL)) {
                $this->paymentStateMachine->apply($payment, PaymentTransitions::TRANSITION_FAIL);
            }

            // Marquer la request comme failed
            if ($this->paymentRequestStateMachine->can($paymentRequest, PaymentRequestTransitions::TRANSITION_FAIL)) {
                $this->paymentRequestStateMachine->apply($paymentRequest, PaymentRequestTransitions::TRANSITION_FAIL);
            }

            return;
        }

        // Débiter le wallet (crée automatiquement la transaction)
        $transaction = $wallet->debit($amount, (string) $payment->getId());

        $this->entityManager->persist($wallet);
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        // Marquer le payment comme completed
        if ($this->paymentStateMachine->can($payment, PaymentTransitions::TRANSITION_COMPLETE)) {
            $this->paymentStateMachine->apply($payment, PaymentTransitions::TRANSITION_COMPLETE);
        }

        // Marquer la request comme completed
        if ($this->paymentRequestStateMachine->can($paymentRequest, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
            $this->paymentRequestStateMachine->apply($paymentRequest, PaymentRequestTransitions::TRANSITION_COMPLETE);
        }
    }
}
