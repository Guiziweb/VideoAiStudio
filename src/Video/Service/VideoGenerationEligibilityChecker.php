<?php

declare(strict_types=1);

namespace App\Video\Service;

use App\Entity\Customer\Customer;
use App\Shared\Payment\PaymentProviderInterface;

final readonly class VideoGenerationEligibilityChecker
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
        private VideoGenerationCostCalculator $costCalculator,
    ) {
    }

    public function canGenerate(Customer $customer): bool
    {
        $cost = $this->costCalculator->getGenerationCost();

        return $this->paymentProvider->canAfford($customer, $cost);
    }
}
