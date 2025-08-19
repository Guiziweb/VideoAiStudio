<?php

declare(strict_types=1);

namespace App\Video\Service;

use App\Video\Entity\VideoGeneration;
use App\Video\Gateway\VideoProviderGatewayInterface;
use App\Video\VideoGenerationTransitions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Service responsible ONLY for workflow transitions
 */
class VideoWorkflowManager
{
    public function __construct(
        #[Autowire(service: 'state_machine.video_generation')]
        private WorkflowInterface $videoGenerationWorkflow,
        private EntityManagerInterface $entityManager,
        private VideoProviderGatewayInterface $providerService,
        private VideoStatusSchedulerInterface $statusScheduler,
    ) {
    }

    /**
     * Submit a video generation to the external provider
     */
    public function submitToProvider(VideoGeneration $generation): bool
    {
        $result = $this->providerService->submitJob($generation);

        if (!$result) {
            return false;
        }

        $generation->setExternalProvider($result['provider']);
        $generation->setExternalJobId($result['job_id']);
        $generation->setExternalSubmittedAt(new \DateTime());
        $generation->setExternalMetadata($result['metadata'] ?? []);

        if (!$this->applyTransition($generation, 'submit')) {
            return false;
        }

        $this->statusScheduler->schedule($generation->getId());

        return true;
    }

    /**
     * Update status from external provider
     */
    public function updateFromProvider(VideoGeneration $generation): bool
    {
        $providerStatus = $this->providerService->getJobStatus($generation);
        if (!$providerStatus) {
            return false;
        }

        $currentState = $generation->getWorkflowState();

        // Direct mapping from provider status to workflow action
        return match ($providerStatus) {
            VideoGenerationTransitions::STATE_PROCESSING => $currentState === VideoGenerationTransitions::STATE_SUBMITTED && $this->markAsProcessing($generation),
            VideoGenerationTransitions::STATE_COMPLETED => $currentState === VideoGenerationTransitions::STATE_PROCESSING && $this->markAsCompleted($generation),
            VideoGenerationTransitions::STATE_FAILED => in_array($currentState, [
                    VideoGenerationTransitions::STATE_SUBMITTED,
                    VideoGenerationTransitions::STATE_PROCESSING,
                ]) && $this->markAsFailed($generation, 'Provider job failed'),
            default => false,
        };
    }

    /**
     * Mark generation as processing
     */
    public function markAsProcessing(VideoGeneration $generation): bool
    {
        return $this->applyTransition($generation, 'start_processing');
    }

    /**
     * Mark generation as completed with video URL
     */
    public function markAsCompleted(VideoGeneration $generation): bool
    {
        // Get result from provider if available
        if ($generation->getExternalJobId()) {
            $result = $this->providerService->getJobResult($generation->getExternalJobId());
            if ($result) {
                $generation->setVideoStorageUrl($result['video_url'] ?? null);
            }
        }

        return $this->applyTransition($generation, 'complete');
    }

    /**
     * Mark generation as failed
     */
    public function markAsFailed(VideoGeneration $generation, string $reason = ''): bool
    {
        $generation->setExternalErrorMessage($reason);

        return $this->applyTransition($generation, 'fail');
    }

    /**
     * Apply workflow transition
     */
    private function applyTransition(VideoGeneration $generation, string $transition): bool
    {
        if (!$this->videoGenerationWorkflow->can($generation, $transition)) {
            return false;
        }

        $this->videoGenerationWorkflow->apply($generation, $transition);
        $this->entityManager->flush();

        return true;
    }
}
