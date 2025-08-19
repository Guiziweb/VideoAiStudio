<?php

declare(strict_types=1);

namespace App\Video\Entity\Trait;

use App\Video\VideoGenerationTransitions;

trait WorkflowStatusTrait
{
    public function isStatus(string $status): bool
    {
        return $this->workflowState === $status;
    }

    public function isCreated(): bool
    {
        return $this->isStatus(VideoGenerationTransitions::STATE_CREATED);
    }

    public function isSubmitted(): bool
    {
        return $this->isStatus(VideoGenerationTransitions::STATE_SUBMITTED);
    }

    public function isProcessing(): bool
    {
        return $this->isStatus(VideoGenerationTransitions::STATE_PROCESSING);
    }

    public function isCompleted(): bool
    {
        return $this->isStatus(VideoGenerationTransitions::STATE_COMPLETED);
    }

    public function isFailed(): bool
    {
        return $this->isStatus(VideoGenerationTransitions::STATE_FAILED);
    }

    public function isRefunded(): bool
    {
        return $this->isStatus(VideoGenerationTransitions::STATE_REFUNDED);
    }

    public function isFinalState(): bool
    {
        return $this->isCompleted() || $this->isFailed() || $this->isRefunded();
    }

    public function isInProgress(): bool
    {
        return $this->isSubmitted() || $this->isProcessing();
    }

    /**
     * Get simplified status label for customer display
     * Groups technical statuses into user-friendly labels
     */
    public function getCustomerStatusLabel(): string
    {
        return match ($this->workflowState) {
            VideoGenerationTransitions::STATE_CREATED => 'created',
            VideoGenerationTransitions::STATE_SUBMITTED,
            VideoGenerationTransitions::STATE_PROCESSING => 'processing',
            VideoGenerationTransitions::STATE_COMPLETED => 'completed',
            VideoGenerationTransitions::STATE_FAILED => 'failed',
            VideoGenerationTransitions::STATE_REFUNDED => 'refunded',
            default => $this->workflowState,
        };
    }
}
