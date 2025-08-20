<?php

declare(strict_types=1);

namespace App\Shared\Entity\Customer;

use App\Wallet\Entity\HasWalletTrait;
use App\Wallet\Entity\Wallet;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Customer as BaseCustomer;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_customer')]
class Customer extends BaseCustomer
{
    use HasWalletTrait;

    public function __construct()
    {
        parent::__construct();

        // Créer automatiquement un wallet pour chaque nouveau customer
        if ($this->wallet === null) {
            $wallet = new Wallet();
            $wallet->setCustomer($this);
            $wallet->initializeBalance();
            $this->setWallet($wallet);
        }
    }
}
