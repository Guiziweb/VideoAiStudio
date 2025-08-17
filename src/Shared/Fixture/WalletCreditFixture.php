<?php

declare(strict_types=1);

namespace App\Shared\Fixture;

use App\Entity\Customer\Customer;
use App\Wallet\Entity\Wallet;
use App\Wallet\Enum\TransactionType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class WalletCreditFixture extends AbstractFixture
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function load(array $options): void
    {
        foreach ($options['credits'] as $creditData) {
            $customer = $this->customerRepository->findOneBy(['email' => $creditData['customer_email']]);

            if (!$customer instanceof Customer) {
                continue;
            }

            $wallet = $customer->getWallet();
            if (!$wallet) {
                $wallet = new Wallet();
                $wallet->setCustomer($customer);
                $wallet->initializeBalance();
                $this->entityManager->persist($wallet);
            }

            $amount = $creditData['amount'];
            $createdAt = isset($creditData['created_at'])
                ? new \DateTime($creditData['created_at'])
                : null;
            $reference = $creditData['reference'] ?? '';

            // Créditer avec date et référence personnalisées (crée automatiquement la transaction)
            $transaction = $wallet->credit($amount, $reference, $createdAt);
            $this->entityManager->persist($transaction);
        }

        // Traiter les transactions définies en YAML
        if (isset($options['transactions'])) {
            $this->processTransactions($options['transactions']);
        }

        $this->entityManager->flush();
    }

    private function processTransactions(array $transactions): void
    {
        foreach ($transactions as $transactionData) {
            $customer = $this->customerRepository->findOneBy(['email' => $transactionData['customer_email']]);

            if (!$customer instanceof Customer) {
                continue;
            }

            $wallet = $customer->getWallet();
            if (!$wallet) {
                continue;
            }

            $amount = $transactionData['amount'];
            $typeString = $transactionData['type'];
            $reference = $transactionData['reference'] ?? '';

            // Calculer la date basée sur days_ago ou utiliser created_at
            if (isset($transactionData['days_ago'])) {
                $daysAgo = (int) $transactionData['days_ago'];
                $createdAt = (new \DateTime())->sub(new \DateInterval("P{$daysAgo}D"));
            } elseif (isset($transactionData['created_at'])) {
                $createdAt = new \DateTime($transactionData['created_at']);
            } else {
                $createdAt = null;
            }

            // Convertir le string en enum
            try {
                $type = TransactionType::fromString($typeString);
            } catch (\InvalidArgumentException) {
                continue; // Skip si type invalide
            }

            if ($type === TransactionType::DEBIT && str_contains($reference, 'VIDEO_GENERATION')) {
                $amount = 1000;
            }

            // Créer la transaction selon le type
            $transaction = match ($type) {
                TransactionType::CREDIT => $wallet->credit($amount, $reference, $createdAt),
                TransactionType::DEBIT => $wallet->canAfford($amount)
                    ? $wallet->debit($amount, $reference, $createdAt)
                    : null,
            };

            if ($transaction === null) {
                continue; // Skip si pas assez de fonds
            }

            $this->entityManager->persist($transaction);
        }
    }

    public function getName(): string
    {
        return 'wallet_credit';
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('credits')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('customer_email')->isRequired()->end()
                            ->integerNode('amount')->isRequired()->end()
                            ->scalarNode('created_at')->end()
                            ->scalarNode('reference')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('transactions')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('customer_email')->isRequired()->end()
                            ->integerNode('amount')->isRequired()->end()
                            ->scalarNode('type')->isRequired()->end()
                            ->scalarNode('reference')->end()
                            ->scalarNode('created_at')->end()
                            ->integerNode('days_ago')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
