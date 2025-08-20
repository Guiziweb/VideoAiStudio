<?php

declare(strict_types=1);

namespace App\Wallet\EventListener;

use App\Shared\Entity\Customer\Customer;
use App\Wallet\Entity\Wallet;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.customer.post_create', method: 'onCustomerCreate')]
final readonly class CustomerWalletCreationListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onCustomerCreate(ResourceControllerEvent $event): void
    {
        $customer = $event->getSubject();

        if (!$customer instanceof Customer) {
            return;
        }

        $wallet = new Wallet();
        $wallet->setCustomer($customer);
        $wallet->initializeBalance();

        $this->entityManager->persist($wallet);
        $this->entityManager->flush();
    }
}
