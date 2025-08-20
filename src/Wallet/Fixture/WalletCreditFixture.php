<?php

declare(strict_types=1);

namespace App\Wallet\Fixture;

use App\Shared\Entity\Customer\Customer;
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
            $amount = $creditData['amount'];

            $wallet->credit($amount);
            $this->entityManager->persist($wallet);
        }

        $this->entityManager->flush();
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
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
