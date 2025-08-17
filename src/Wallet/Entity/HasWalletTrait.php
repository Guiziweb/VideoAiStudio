<?php

declare(strict_types=1);

namespace App\Wallet\Entity;

use Doctrine\ORM\Mapping as ORM;

trait HasWalletTrait
{
    #[ORM\OneToOne(mappedBy: 'customer', targetEntity: Wallet::class, cascade: ['persist', 'remove'])]
    private ?Wallet $wallet = null;

    public function getWallet(): ?Wallet
    {
        return $this->wallet;
    }

    public function setWallet(Wallet $wallet): self
    {
        $this->wallet = $wallet;

        return $this;
    }
}
