<?php

declare(strict_types=1);

namespace App\Wallet\Payment\Command;

final readonly class PayWalletCommand
{
    public function __construct(
        private string $paymentRequestId,
    ) {
    }

    public function getPaymentRequestId(): string
    {
        return $this->paymentRequestId;
    }
}
