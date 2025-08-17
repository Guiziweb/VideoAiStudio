<?php

declare(strict_types=1);

namespace App\Wallet\Enum;

enum TransactionType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    public function getLabel(): string
    {
        return match ($this) {
            self::CREDIT => 'Crédit',
            self::DEBIT => 'Débit',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::CREDIT => 'bg-success',
            self::DEBIT => 'bg-danger',
        };
    }

    public static function fromString(string $value): self
    {
        return match ($value) {
            'credit' => self::CREDIT,
            'debit' => self::DEBIT,
            default => throw new \InvalidArgumentException("Invalid transaction type: $value"),
        };
    }

    public static function getLabelForValue(string $value): string
    {
        return self::fromString($value)->getLabel();
    }

    public static function getBadgeClassForValue(string $value): string
    {
        return self::fromString($value)->getBadgeClass();
    }
}
