<?php

declare(strict_types=1);

namespace App\Video\Service;

use App\Video\Message\CheckVideoStatusMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Service responsible for scheduling video status checks
 */
readonly class VideoStatusScheduler implements VideoStatusSchedulerInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        #[Autowire('%app.video.status_check_delay%')]
        private int $statusCheckDelay,
    ) {
    }

    /**
     * Schedule a status check for a video generation
     */
    public function schedule(int $generationId): void
    {
        $this->bus->dispatch(
            new CheckVideoStatusMessage($generationId),
            [new DelayStamp($this->statusCheckDelay)],
        );
    }
}
