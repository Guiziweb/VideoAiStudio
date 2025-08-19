<?php

declare(strict_types=1);

namespace App\Video\MessageHandler;

use App\Video\Message\CheckVideoStatusMessage;
use App\Video\Repository\VideoGenerationRepository;
use App\Video\Service\VideoStatusSchedulerInterface;
use App\Video\Service\VideoWorkflowManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckVideoStatusHandler
{
    public function __construct(
        private VideoGenerationRepository $repository,
        private VideoWorkflowManager $workflowManager,
        private VideoStatusSchedulerInterface $statusScheduler,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CheckVideoStatusMessage $message): void
    {
        $generation = $this->repository->find($message->videoGenerationId);

        if (!$generation) {
            return;
        }
        if (!$generation->getExternalJobId()) {
            return;
        }

        try {
            $this->workflowManager->updateFromProvider($generation);

            // Schedule next check if still in progress
            if ($generation->isInProgress()) {
                $this->statusScheduler->schedule($generation->getId());
            } elseif ($generation->isFinalState()) {
                $this->logger->info('Video generation reached final state', [
                    'generation_id' => $generation->getId(),
                    'final_state' => $generation->getWorkflowState(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to check video generation status', [
                'generation_id' => $generation->getId(),
                'error' => $e->getMessage(),
            ]);

            // Schedule retry
            $this->statusScheduler->schedule($generation->getId());
        }
    }
}
