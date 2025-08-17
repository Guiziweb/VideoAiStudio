<?php

declare(strict_types=1);

namespace App\Wallet\Component;

use App\Wallet\Entity\Wallet;
use App\Wallet\Enum\TransactionType;
use App\Wallet\Repository\WalletTransactionRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('wallet_transaction_chart')]
final class WalletTransactionChartComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public Wallet $wallet;

    #[LiveProp]
    public string $period = '2 weeks';

    #[LiveProp]
    public string $interval = 'day';

    #[LiveProp]
    public ?\DateTime $startDate = null;

    #[LiveProp]
    public ?\DateTime $endDate = null;

    public function __construct(
        private readonly WalletTransactionRepository $transactionRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getChartData(): array
    {
        [$startDate, $endDate] = $this->getDateRange();

        $transactions = $this->transactionRepository->findTransactionsByWalletAndPeriod(
            $this->wallet->getId(),
            $startDate,
            $endDate,
        );

        $intervals = [];
        $balances = [];

        // Générer les intervalles selon la période (limité à 100 points max)
        $format = $this->interval === 'day' ? 'Y-m-d' : 'Y-m';
        $intervalPeriod = $this->interval === 'day' ? 'P1D' : 'P1M';

        $current = clone $startDate;
        $count = 0;
        while ($current <= $endDate && $count < 100) {
            $key = $current->format($format);
            $intervals[] = $key;
            $balances[$key] = 0;
            $current->add(new \DateInterval($intervalPeriod));
            ++$count;
        }

        // Calculer l'évolution du solde jour par jour
        $currentBalance = $this->wallet->getBalance();

        // Remonter dans le temps : soustraire toutes les transactions futures
        foreach ($transactions as $transaction) {
            $type = $transaction->getType();
            if ($type === TransactionType::CREDIT) {
                $currentBalance -= $transaction->getAmount();
            } elseif ($type === TransactionType::DEBIT) {
                $currentBalance += $transaction->getAmount();
            }
        }

        // Grouper les transactions par date
        $transactionsByDate = [];
        foreach ($transactions as $transaction) {
            $key = $transaction->getCreatedAt()->format($format);
            if (!isset($transactionsByDate[$key])) {
                $transactionsByDate[$key] = [];
            }
            $transactionsByDate[$key][] = $transaction;
        }

        // Calculer le solde pour chaque jour
        $runningBalance = $currentBalance;
        foreach ($intervals as $interval) {
            if (isset($transactionsByDate[$interval])) {
                // Il y a des transactions ce jour-là
                foreach ($transactionsByDate[$interval] as $transaction) {
                    $type = $transaction->getType();
                    if ($type === TransactionType::CREDIT) {
                        $runningBalance += $transaction->getAmount();
                    } elseif ($type === TransactionType::DEBIT) {
                        $runningBalance -= $transaction->getAmount();
                    }
                }
            }
            $balances[$interval] = $runningBalance;
        }

        return [
            'intervals' => $intervals,
            'balances' => array_values($balances),
        ];
    }

    /**
     * @return array{\DateTime, \DateTime}
     */
    private function getDateRange(): array
    {
        if ($this->startDate && $this->endDate) {
            return [$this->startDate, $this->endDate];
        }

        $endDate = new \DateTime();
        $startDate = match ($this->period) {
            '2 weeks' => (clone $endDate)->sub(new \DateInterval('P14D')),
            'month' => (clone $endDate)->sub(new \DateInterval('P1M')),
            'year' => (clone $endDate)->sub(new \DateInterval('P1Y')),
            default => (clone $endDate)->sub(new \DateInterval('P14D')),
        };

        return [$startDate, $endDate];
    }

    #[LiveAction]
    public function changeRange(): void
    {
        // Reset des dates personnalisées quand on change de période
        $this->startDate = null;
        $this->endDate = null;
    }

    #[LiveAction]
    public function getPreviousPeriod(): void
    {
        [$currentStart, $currentEnd] = $this->getDateRange();

        $interval = match ($this->period) {
            '2 weeks' => 'P14D',
            'month' => 'P1M',
            'year' => 'P1Y',
            default => 'P14D',
        };

        $this->endDate = clone $currentStart;
        $this->startDate = (clone $this->endDate)->sub(new \DateInterval($interval));
    }

    #[LiveAction]
    public function getNextPeriod(): void
    {
        [$currentStart, $currentEnd] = $this->getDateRange();

        $interval = match ($this->period) {
            '2 weeks' => 'P14D',
            'month' => 'P1M',
            'year' => 'P1Y',
            default => 'P14D',
        };

        $this->startDate = clone $currentEnd;
        $this->endDate = (clone $this->startDate)->add(new \DateInterval($interval));

        // Ne pas aller dans le futur
        $now = new \DateTime();
        if ($this->endDate > $now) {
            $this->endDate = $now;
        }
    }
}
