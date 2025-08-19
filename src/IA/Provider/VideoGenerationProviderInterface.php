<?php

declare(strict_types=1);

namespace App\IA\Provider;

use App\IA\Provider\Exception\ProviderException;
use App\Video\Entity\VideoGeneration;

/**
 * Generic interface for video generation providers (RunPod, Replicate, etc.)
 */
interface VideoGenerationProviderInterface
{
    /**
     * Submit a video generation job to the provider
     *
     * @return array{provider: string, job_id: string, metadata?: array<string, mixed>}
     *
     * @throws ProviderException
     */
    public function submitJob(VideoGeneration $videoGeneration): array;

    /**
     * Get the current status of a job
     * Returns the raw status from the provider (provider-specific)
     *
     * @throws ProviderException
     */
    public function getJobStatus(VideoGeneration $videoGeneration): string;

    /**
     * Map provider-specific status to workflow state
     * Each provider implements its own mapping logic
     *
     * @return string One of VideoGenerationTransitions constants (STATE_*)
     */
    public function mapToNormalizedStatus(string $providerStatus): string;

    /**
     * Get the result URL/data for a completed job
     *
     * @return array{video_url: string, metadata?: array<string, mixed>}
     *
     * @throws ProviderException
     */
    public function getJobResult(string $externalJobId): array;

    /**
     * Cancel a running job (if supported)
     *
     * @throws ProviderException
     */
    public function cancelJob(string $externalJobId): bool;

    /**
     * Get provider type identifier
     */
    public function getType(): string;

    /**
     * Check if provider is available/healthy
     */
    public function isHealthy(): bool;
}
