<?php

declare(strict_types=1);

namespace App\Video\Gateway;

use App\IA\Provider\Exception\ProviderException;
use App\IA\Provider\VideoGenerationProviderInterface;
use App\Video\Entity\VideoGeneration;
use Psr\Log\LoggerInterface;

/**
 * Service responsible for AI provider interactions only
 */
class VideoProviderGateway implements VideoProviderGatewayInterface
{
    public function __construct(
        private VideoGenerationProviderInterface $provider,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Submit a video generation to the external provider
     *
     * @return array{provider: string, job_id: string, metadata?: array<string, mixed>}|null
     */
    public function submitJob(VideoGeneration $generation): ?array
    {
        try {
            return $this->provider->submitJob($generation);
        } catch (ProviderException $e) {
            $this->logger->error('Provider submission failed', [
                'generation_id' => $generation->getId(),
                'error' => $e->getMessage(),
                'provider' => $e->getProviderType(),
                'job_id' => $e->getExternalJobId(),
            ]);

            return null;
        }
    }

    /**
     * Get current job status from provider
     * Returns workflow state constant (STATE_*)
     */
    public function getJobStatus(VideoGeneration $generation): ?string
    {
        if (!$generation->getExternalJobId()) {
            return null;
        }

        try {
            $rawStatus = $this->provider->getJobStatus($generation);

            return $this->provider->mapToNormalizedStatus($rawStatus);
        } catch (ProviderException $e) {
            $this->logger->error('Failed to get job status from provider', [
                'generation_id' => $generation->getId(),
                'external_job_id' => $generation->getExternalJobId(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get job result for completed generation
     *
     * @return array{video_url: string, metadata?: array<string, mixed>}|null
     */
    public function getJobResult(string $externalJobId): ?array
    {
        try {
            return $this->provider->getJobResult($externalJobId);
        } catch (ProviderException $e) {
            $this->logger->error('Failed to get job result from provider', [
                'external_job_id' => $externalJobId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
