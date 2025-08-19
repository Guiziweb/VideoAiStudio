<?php

declare(strict_types=1);

namespace App\IA\Provider;

use App\IA\Provider\Exception\ProviderException;
use App\Video\Entity\VideoGeneration;
use App\Video\VideoGenerationTransitions;

/**
 * Example RunPod provider implementation
 * Shows how a real provider would map its specific statuses
 */
final class RunpodProvider implements VideoGenerationProviderInterface
{
    // RunPod specific status constants
    private const STATUS_IN_QUEUE = 'IN_QUEUE';

    private const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    private const STATUS_COMPLETED = 'COMPLETED';

    private const STATUS_FAILED = 'FAILED';

    private const STATUS_CANCELLED = 'CANCELLED';

    private const STATUS_TIMED_OUT = 'TIMED_OUT';

    public function __construct(
        private string $apiKey,
        private string $endpoint,
    ) {
    }

    public function submitJob(VideoGeneration $videoGeneration): array
    {
        // Call RunPod API to submit job using $this->apiKey and $this->endpoint
        // This is a simplified example
        if (!$this->apiKey) {
            throw new ProviderException('API key not configured', 0, null, 'runpod');
        }

        $jobId = 'runpod_' . uniqid();

        return [
            'provider' => $this->getType(),
            'job_id' => $jobId,
            'metadata' => [
                'endpoint_id' => $this->endpoint,
                'submitted_at' => (new \DateTime())->format('c'),
            ],
        ];
    }

    public function getJobStatus(VideoGeneration $videoGeneration): string
    {
        $externalJobId = $videoGeneration->getExternalJobId();
        if (!$externalJobId || !str_starts_with($externalJobId, 'runpod_')) {
            throw new ProviderException("Invalid RunPod job ID: {$externalJobId}", 0, null, $this->getType(), $externalJobId);
        }

        // Here you would call RunPod API to get actual status
        // Example response from RunPod:
        // return $this->callRunPodApi("/status/{$externalJobId}");

        // For now, return a mock RunPod status
        return self::STATUS_IN_PROGRESS;
    }

    public function mapToNormalizedStatus(string $providerStatus): string
    {
        // Map RunPod specific statuses to workflow states
        return match ($providerStatus) {
            self::STATUS_IN_PROGRESS, self::STATUS_IN_QUEUE => VideoGenerationTransitions::STATE_PROCESSING,
            self::STATUS_COMPLETED => VideoGenerationTransitions::STATE_COMPLETED,
            self::STATUS_FAILED, self::STATUS_TIMED_OUT, self::STATUS_CANCELLED => VideoGenerationTransitions::STATE_FAILED,
            default => VideoGenerationTransitions::STATE_SUBMITTED,
        };
    }

    public function getJobResult(string $externalJobId): array
    {
        // Call RunPod API to get result
        // return $this->callRunPodApi("/result/{$externalJobId}");

        return [
            'video_url' => "https://runpod-storage.com/videos/{$externalJobId}.mp4",
            'metadata' => [
                'duration' => 30,
                'resolution' => '1920x1080',
            ],
        ];
    }

    public function cancelJob(string $externalJobId): bool
    {
        // Call RunPod API to cancel job
        // return $this->callRunPodApi("/cancel/{$externalJobId}", 'POST');

        return true;
    }

    public function getType(): string
    {
        return 'runpod';
    }

    public function isHealthy(): bool
    {
        // Check RunPod API health
        // return $this->callRunPodApi("/health");

        return true;
    }
}
