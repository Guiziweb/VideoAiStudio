<?php

declare(strict_types=1);

namespace App\IA\Provider;

use App\IA\Provider\Exception\ProviderException;
use App\Video\Entity\VideoGeneration;
use App\Video\VideoGenerationTransitions;

/**
 * Mock provider for testing video generation workflow with realistic timing
 */
final class MockProvider implements VideoGenerationProviderInterface
{
    private bool $healthy = true;

    public function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function submitJob(VideoGeneration $videoGeneration): array
    {
        if (!$this->healthy) {
            throw new ProviderException('Mock provider is not healthy', 0, null, $this->getType());
        }

        $jobId = 'mock_' . uniqid();

        return [
            'provider' => $this->getType(),
            'job_id' => $jobId,
            'metadata' => [
                'prompt_length' => strlen($videoGeneration->getPrompt()),
                'submitted_at' => (new \DateTime())->format('c'),
            ],
        ];
    }

    public function getJobStatus(VideoGeneration $videoGeneration): string
    {
        $externalJobId = $videoGeneration->getExternalJobId();
        if (!$externalJobId || !str_starts_with($externalJobId, 'mock_')) {
            throw new ProviderException("Invalid mock job ID: {$externalJobId}", 0, null, $this->getType(), $externalJobId);
        }

        // Check if job should fail based on prompt (for testing)
        if ($this->shouldFailBasedOnPrompt($videoGeneration->getPrompt())) {
            return 'MOCK_ERROR';
        }

        // Simple time-based progression for development
        $submittedAt = $videoGeneration->getExternalSubmittedAt();
        if (!$submittedAt) {
            return 'MOCK_PENDING';
        }

        $ageInSeconds = time() - $submittedAt->getTimestamp();

        if ($ageInSeconds < 10) {
            return 'MOCK_QUEUED';        // 0-10s: queued (submitted)
        }
        if ($ageInSeconds < 30) {
            return 'MOCK_RENDERING';     // 10-30s: rendering (processing)
        }

        return 'MOCK_DONE';          // 30s+: completed
    }

    public function mapToNormalizedStatus(string $providerStatus): string
    {
        // Map Mock provider specific statuses to workflow states
        return match ($providerStatus) {
            'MOCK_PENDING',
            'MOCK_QUEUED' => VideoGenerationTransitions::STATE_SUBMITTED,
            'MOCK_RENDERING' => VideoGenerationTransitions::STATE_PROCESSING,
            'MOCK_DONE' => VideoGenerationTransitions::STATE_COMPLETED,
            'MOCK_ERROR' => VideoGenerationTransitions::STATE_FAILED,
            default => VideoGenerationTransitions::STATE_SUBMITTED,
        };
    }

    /**
     * Check if prompt indicates failure (for testing)
     */
    private function shouldFailBasedOnPrompt(string $prompt): bool
    {
        $failureKeywords = ['fail', 'error', 'crash'];
        $lowerPrompt = strtolower($prompt);

        foreach ($failureKeywords as $keyword) {
            if (str_contains($lowerPrompt, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJobResult(string $externalJobId): array
    {
        // For getJobResult we can assume the job is completed since this method
        // is only called when the workflow is already in completed state
        return [
            'video_url' => "https://mock-s3-bucket.com/videos/{$externalJobId}.mp4",
            'metadata' => [
                'duration' => 30, // 30 seconds
                'resolution' => '1920x1080',
                'format' => 'mp4',
                'file_size' => 2048576, // 2MB
            ],
        ];
    }

    public function cancelJob(string $externalJobId): bool
    {
        // Pour le mock, on accepte toujours l'annulation
        return str_starts_with($externalJobId, 'mock_');
    }

    public function getType(): string
    {
        return 'mock';
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }
}
