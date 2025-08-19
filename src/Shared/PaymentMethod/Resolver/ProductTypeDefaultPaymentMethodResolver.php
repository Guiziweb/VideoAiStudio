<?php

declare(strict_types=1);

namespace App\Shared\PaymentMethod\Resolver;

use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Resolver\DefaultPaymentMethodResolverInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

// HACK: We decorate PayPal resolver instead of base Sylius resolver because:
// - PayPal plugin decorates sylius.resolver.payment_methods and uses our ProductTypePaymentMethodsResolver
// - Decorating the base resolver would bypass our filtering logic
// - This ensures we get the properly filtered payment methods
#[AsDecorator('sylius_paypal.resolver.payment_method.paypal')]
final readonly class ProductTypeDefaultPaymentMethodResolver implements DefaultPaymentMethodResolverInterface
{
    public function __construct(
        #[AutowireDecorated]
        private DefaultPaymentMethodResolverInterface $decoratedDefaultPaymentMethodResolver,
        private PaymentMethodsResolverInterface $paymentMethodsResolver,
    ) {
    }

    public function getDefaultPaymentMethod(BasePaymentInterface $payment): PaymentMethodInterface
    {
        // Utiliser le resolver de méthodes pour obtenir les méthodes filtrées
        $supportedMethods = $this->paymentMethodsResolver->getSupportedMethods($payment);

        if (empty($supportedMethods)) {
            // Fallback to decorated resolver if no methods found
            return $this->decoratedDefaultPaymentMethodResolver->getDefaultPaymentMethod($payment);
        }

        // Retourner la première méthode supportée après filtrage
        return reset($supportedMethods);
    }
}
