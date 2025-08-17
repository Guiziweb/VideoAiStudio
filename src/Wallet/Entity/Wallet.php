<?php

declare(strict_types=1);

namespace App\Wallet\Entity;

use App\Entity\Customer\Customer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;

#[AsResource(
    alias: 'app.wallet',
    section: 'admin',
    templatesDir: '@SyliusAdmin/shared/crud',
    routePrefix: '/admin',
    driver: 'doctrine/orm',
    vars: [
        'header' => 'app.ui.wallets',
        'subheader' => 'app.ui.wallet',
    ],
    operations: [
        new Index(grid: 'app_admin_wallet'),
        new Show(
            vars: [
                'subheader' => 'app.ui.wallet_details',
                'wallet_transactions_grid' => 'app_admin_wallet_transactions_for_wallet',
            ],
        ),
    ],
)]
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

    /** @var Collection<int, WalletTransaction> */
    #[ORM\OneToMany(mappedBy: 'wallet', targetEntity: WalletTransaction::class)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $transactions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->transactions = new ArrayCollection();
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

    public function credit(int $amount, string $reference = '', ?\DateTime $createdAt = null): WalletTransaction
    {
        $this->balance += $amount;

        $date = $createdAt ?: new \DateTime();

        $transaction = new WalletTransaction();
        $transaction->setWallet($this);
        $transaction->setType(\App\Wallet\Enum\TransactionType::CREDIT);
        $transaction->setAmount($amount);
        $transaction->setReference($reference);
        $transaction->setCreatedAt($date);
        $transaction->setUpdatedAt($date);

        $this->transactions->add($transaction);

        return $transaction;
    }

    public function debit(int $amount, string $reference = '', ?\DateTime $createdAt = null): WalletTransaction
    {
        if ($amount > $this->balance) {
            throw new \InvalidArgumentException('app.ui.insufficient_balance');
        }
        $this->balance -= $amount;

        $date = $createdAt ?: new \DateTime();

        $transaction = new WalletTransaction();
        $transaction->setWallet($this);
        $transaction->setType(\App\Wallet\Enum\TransactionType::DEBIT);
        $transaction->setAmount($amount);
        $transaction->setReference($reference);
        $transaction->setCreatedAt($date);
        $transaction->setUpdatedAt($date);

        $this->transactions->add($transaction);

        return $transaction;
    }

    public function canAfford(int $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * @return Collection<int, WalletTransaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }
}
