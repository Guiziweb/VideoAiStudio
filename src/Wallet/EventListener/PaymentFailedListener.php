<?php

declare(strict_types=1);

namespace App\Wallet\EventListener;

use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: 'workflow.sylius_payment.completed.fail', method: 'onPaymentFailed')]
final readonly class PaymentFailedListener
{
    public function __construct(
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    public function onPaymentFailed(CompletedEvent $event): void
    {
        $payment = $event->getSubject();

        if (!$payment instanceof PaymentInterface) {
            return;
        }

        // Vérifier si c'est un payment wallet qui a échoué pour fonds insuffisants
        $details = $payment->getDetails();
        if (isset($details['error']) && $details['error'] === 'Insufficient funds') {
            $session = $this->requestStack->getSession();

            $session->getFlashBag()->add(
                'error',
                $this->translator->trans('app.ui.insufficient_funds_message', [], 'messages'),
            );
        }
    }
}
