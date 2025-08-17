<?php

declare(strict_types=1);

namespace App\Wallet\Payment\HttpResponseProvider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AutoconfigureTag('sylius.payment_request.http_response_provider', ['gateway_factory' => 'wallet'])]
final readonly class PayWalletHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): bool {
        $payment = $paymentRequest->getPayment();

        return $payment instanceof PaymentInterface &&
            $payment->getMethod()?->getGatewayConfig()?->getFactoryName() === 'wallet';
    }

    public function getResponse(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): Response {
        $payment = $paymentRequest->getPayment();
        if (!$payment instanceof PaymentInterface) {
            throw new \InvalidArgumentException('Payment not found');
        }

        $order = $payment->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new \InvalidArgumentException('Order not found');
        }

        if ($paymentRequest->getState() === PaymentRequestInterface::STATE_COMPLETED) {
            return new RedirectResponse(
                $this->urlGenerator->generate('sylius_shop_order_thank_you', [
                    'tokenValue' => $order->getTokenValue(),
                ]),
            );
        }

        if ($paymentRequest->getState() === PaymentRequestInterface::STATE_FAILED) {
            // Rediriger vers le choix du paiement
            return new RedirectResponse(
                $this->urlGenerator->generate('sylius_shop_checkout_select_payment', [
                    'tokenValue' => $order->getTokenValue(),
                ]),
            );
        }

        // État en cours ou nouveau - rediriger vers la page de paiement
        return new RedirectResponse(
            $this->urlGenerator->generate('sylius_shop_checkout_complete'),
        );
    }
}
