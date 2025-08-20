<?php

declare(strict_types=1);

namespace App\Wallet\Entity;

use App\Shared\Entity\Customer\Customer;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Sylius\Component\Resource\Model\ResourceInterface;

#[ORM\Entity]
class Wallet implements ResourceInterface
{
    use TimestampableEntity;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Customer::class, inversedBy: 'wallet')]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(type: 'integer')]
    private int $balance = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function initializeBalance(): void
    {
        $this->balance = 0;
    }

    public function credit(int $amount): void
    {
        $this->balance += $amount;
    }

    public function debit(int $amount): void
    {
        if ($amount > $this->balance) {
            throw new \InvalidArgumentException('app.ui.insufficient_balance');
        }
        $this->balance -= $amount;
    }

    public function canAfford(int $amount): bool
    {
        return $this->balance >= $amount;
    }
}
