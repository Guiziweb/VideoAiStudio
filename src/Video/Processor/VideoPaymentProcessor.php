<?php

declare(strict_types=1);

namespace App\Video\Processor;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Announcer\PaymentRequestAnnouncerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;

final readonly class VideoPaymentProcessor
{
    public function __construct(
        private OrderProcessorInterface $orderProcessor,
        private StateMachineInterface $stateMachine,
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private PaymentRequestAnnouncerInterface $paymentRequestAnnouncer,
    ) {
    }

    public function processPaymentForOrder(OrderInterface $order): void
    {
        $this->orderProcessor->process($order);

        if ($this->stateMachine->can($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CREATE)) {
            $this->stateMachine->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CREATE);
        }

        $payment = $order->getLastPayment();
        if ($payment) {
            $paymentRequest = $this->paymentRequestFactory->create($payment, $payment->getMethod());
            $this->paymentRequestRepository->add($paymentRequest);
            $this->paymentRequestAnnouncer->dispatchPaymentRequestCommand($paymentRequest);
        }
    }
}
