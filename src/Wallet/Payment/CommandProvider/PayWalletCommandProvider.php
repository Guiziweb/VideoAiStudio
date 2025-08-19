<?php

declare(strict_types=1);

namespace App\Wallet\Payment\CommandProvider;

use App\Wallet\Payment\Command\PayWalletCommand;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('sylius.payment_request.command_provider', ['gateway_factory' => 'wallet'])]
final readonly class PayWalletCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        $payment = $paymentRequest->getPayment();

        if (!$payment instanceof PaymentInterface) {
            return false;
        }

        $gatewayConfig = $payment->getMethod()?->getGatewayConfig();
        $factoryName = $gatewayConfig?->getFactoryName();
        $action = $paymentRequest->getAction();

        return $action === PaymentRequestInterface::ACTION_CAPTURE && $factoryName === 'wallet';
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        $id = $paymentRequest->getId();
        if ($id === null) {
            throw new \InvalidArgumentException('Payment request ID cannot be null');
        }

        return new PayWalletCommand($id);
    }
}
