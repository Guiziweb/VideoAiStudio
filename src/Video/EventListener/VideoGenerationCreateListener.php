<?php

declare(strict_types=1);

namespace App\Video\EventListener;

use App\Shared\Payment\PaymentProviderInterface;
use App\Video\Entity\VideoGeneration;
use App\Video\Service\VideoGenerationCostCalculator;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

final class VideoGenerationCreateListener
{
    public function __construct(
        private CustomerContextInterface $customerContext,
        private VideoGenerationCostCalculator $costCalculator,
        private PaymentProviderInterface $paymentProvider,
        private TranslatorInterface $translator,
    ) {
    }

    #[AsEventListener(event: 'app.video_generation.pre_create')]
    public function onPreCreate(ResourceControllerEvent $event): void
    {
        $videoGeneration = $event->getSubject();

        if (!$videoGeneration instanceof VideoGeneration) {
            return;
        }

        // Récupérer le customer connecté
        $customer = $this->customerContext->getCustomer();
        if (!$customer instanceof \App\Entity\Customer\Customer) {
            throw new \RuntimeException('Customer not found');
        }

        $cost = $this->costCalculator->getGenerationCost();

        // Vérifier et traiter le paiement via l'interface
        if (!$this->paymentProvider->canAfford($customer, $cost)) {
            $message = $this->translator->trans('app.ui.insufficient_balance');
            $event->stop($message);

            return;
        }

        try {
            $paymentId = $this->paymentProvider->charge($customer, $cost, 'Video generation');

            // Enregistrer la référence de paiement
            $videoGeneration->setPaymentId($paymentId);
            $videoGeneration->setPaymentType($this->paymentProvider->getType());
        } catch (\RuntimeException $e) {
            $message = $this->translator->trans('app.ui.payment_failed');
            $event->stop($message);

            return;
        }

        // Remplir les champs automatiquement
        $videoGeneration->setCustomer($customer);
        $videoGeneration->setTokenCost($cost);
        $videoGeneration->setStatus('pending');
    }
}
