<?php

declare(strict_types=1);

namespace App\Shared\Payment;

use App\Entity\Customer\Customer;

interface PaymentProviderInterface
{
    /**
     * Vérifie si le customer peut payer le montant
     */
    public function canAfford(Customer $customer, int $amount): bool;

    /**
     * Débite le montant et retourne l'ID de la transaction
     */
    public function charge(Customer $customer, int $amount, string $reason): int;

    /**
     * Type de provider pour identification
     */
    public function getType(): string;
}
