<?php

declare(strict_types=1);

namespace App\Wallet\Service;

use App\Entity\Customer\Customer;
use App\Shared\Payment\PaymentProviderInterface;
use App\Wallet\Entity\Wallet;
use Doctrine\ORM\EntityManagerInterface;

final class WalletPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function canAfford(Customer $customer, int $amount): bool
    {
        $wallet = $customer->getWallet();
        if (!$wallet instanceof Wallet) {
            throw new \RuntimeException('Customer must have a wallet');
        }

        return $wallet->canAfford($amount);
    }

    public function charge(Customer $customer, int $amount, string $reason): int
    {
        $wallet = $customer->getWallet();
        if (!$wallet instanceof Wallet) {
            throw new \RuntimeException('Customer must have a wallet');
        }

        if (!$wallet->canAfford($amount)) {
            throw new \RuntimeException('Insufficient tokens');
        }

        // Débiter le wallet (crée automatiquement la transaction)
        $transaction = $wallet->debit($amount, $reason);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction->getId();
    }

    public function getType(): string
    {
        return 'wallet';
    }
}
