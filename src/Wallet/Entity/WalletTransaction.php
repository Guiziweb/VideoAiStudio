<?php

declare(strict_types=1);

namespace App\Wallet\Entity;

use App\Wallet\Enum\TransactionType;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;

#[AsResource(
    alias: 'app.wallet_transaction',
    section: 'admin',
    templatesDir: '@SyliusAdmin/shared/crud',
    driver: 'doctrine/orm',
    vars: [
        'header' => 'app.ui.wallet_transactions',
        'subheader' => 'app.ui.wallet_transactions',
    ],
    operations: [
        new Index(grid: 'app_admin_wallet_transaction'),
    ],
)]
#[ORM\Entity]
class WalletTransaction implements ResourceInterface
{
    use TimestampableEntity;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private Wallet $wallet;

    #[ORM\Column(type: 'string', length: 10, enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column(type: 'integer')]
    private int $amount;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reference = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    public function setWallet(Wallet $wallet): self
    {
        $this->wallet = $wallet;

        return $this;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function setType(TransactionType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }
}
