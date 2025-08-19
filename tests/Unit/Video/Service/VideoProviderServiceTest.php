<?php

/*
 * This file is part of the VideoAI Studio package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Video\Service;

use App\IA\Provider\Exception\ProviderException;
use App\IA\Provider\VideoGenerationProviderInterface;
use App\Video\Entity\VideoGeneration;
use App\Video\Gateway\VideoProviderGateway;
use App\Video\VideoGenerationTransitions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class VideoProviderServiceTest extends TestCase
{
    private VideoGenerationProviderInterface&MockObject $provider;
    private LoggerInterface&MockObject $logger;
    private VideoProviderGateway $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = $this->createMock(VideoGenerationProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new VideoProviderGateway(
            $this->provider,
            $this->logger
        );
    }

    public function testSubmitJobSuccessfully(): void
    {
        $generation = $this->createVideoGeneration();
        $expectedResult = [
            'provider' => 'test_provider',
            'job_id' => 'test_job_123',
            'metadata' => ['key' => 'value'],
        ];

        $this->provider
            ->expects($this->once())
            ->method('submitJob')
            ->with($generation)
            ->willReturn($expectedResult);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $result = $this->service->submitJob($generation);

        $this->assertSame($expectedResult, $result);
    }

    public function testSubmitJobHandlesProviderException(): void
    {
        $generation = $this->createVideoGeneration();
        $exception = new ProviderException('Provider failed', 500, null, 'test_provider');

        $this->provider
            ->expects($this->once())
            ->method('submitJob')
            ->with($generation)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Provider submission failed',
                [
                    'generation_id' => $generation->getId(),
                    'error' => 'Provider failed',
                    'provider' => 'test_provider',
                    'job_id' => null,
                ]
            );

        $result = $this->service->submitJob($generation);

        $this->assertNull($result);
    }

    public function testGetJobStatusSuccessfully(): void
    {
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('test_job_123');

        $this->provider
            ->expects($this->once())
            ->method('getJobStatus')
            ->with($generation)
            ->willReturn('PROVIDER_PROCESSING');

        $this->provider
            ->expects($this->once())
            ->method('mapToNormalizedStatus')
            ->with('PROVIDER_PROCESSING')
            ->willReturn(VideoGenerationTransitions::STATE_PROCESSING);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $result = $this->service->getJobStatus($generation);

        $this->assertSame(VideoGenerationTransitions::STATE_PROCESSING, $result);
    }

    public function testGetJobStatusReturnsNullWhenNoExternalJobId(): void
    {
        $generation = $this->createVideoGeneration();
        // No external job ID set

        $this->provider
            ->expects($this->never())
            ->method('getJobStatus');

        $result = $this->service->getJobStatus($generation);

        $this->assertNull($result);
    }

    public function testGetJobStatusHandlesProviderException(): void
    {
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('test_job_123');

        $exception = new ProviderException('Status check failed', 404, null, 'test_provider', 'test_job_123');

        $this->provider
            ->expects($this->once())
            ->method('getJobStatus')
            ->with($generation)
            ->willThrowException($exception);

        $this->provider
            ->expects($this->never())
            ->method('mapToNormalizedStatus');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to get job status from provider',
                [
                    'generation_id' => $generation->getId(),
                    'external_job_id' => 'test_job_123',
                    'error' => 'Status check failed',
                ]
            );

        $result = $this->service->getJobStatus($generation);

        $this->assertNull($result);
    }

    public function testGetJobResultSuccessfully(): void
    {
        $externalJobId = 'test_job_123';
        $expectedResult = [
            'video_url' => 'https://example.com/video.mp4',
            'metadata' => [
                'duration' => 30,
                'resolution' => '1920x1080',
            ],
        ];

        $this->provider
            ->expects($this->once())
            ->method('getJobResult')
            ->with($externalJobId)
            ->willReturn($expectedResult);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $result = $this->service->getJobResult($externalJobId);

        $this->assertSame($expectedResult, $result);
    }

    public function testGetJobResultHandlesProviderException(): void
    {
        $externalJobId = 'test_job_123';
        $exception = new ProviderException('Result fetch failed', 500, null, 'test_provider', $externalJobId);

        $this->provider
            ->expects($this->once())
            ->method('getJobResult')
            ->with($externalJobId)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to get job result from provider',
                [
                    'external_job_id' => $externalJobId,
                    'error' => 'Result fetch failed',
                ]
            );

        $result = $this->service->getJobResult($externalJobId);

        $this->assertNull($result);
    }

    public function testGetJobStatusHandlesProviderExceptionDuringMapping(): void
    {
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('test_job_123');

        $this->provider
            ->expects($this->once())
            ->method('getJobStatus')
            ->with($generation)
            ->willReturn('UNKNOWN_STATUS');

        $mappingException = new ProviderException('Unknown status mapping', 0, null, 'test_provider');

        $this->provider
            ->expects($this->once())
            ->method('mapToNormalizedStatus')
            ->with('UNKNOWN_STATUS')
            ->willThrowException($mappingException);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to get job status from provider',
                [
                    'generation_id' => $generation->getId(),
                    'external_job_id' => 'test_job_123',
                    'error' => 'Unknown status mapping',
                ]
            );

        $result = $this->service->getJobStatus($generation);

        $this->assertNull($result);
    }

    private function createVideoGeneration(): VideoGeneration
    {
        $generation = new VideoGeneration();
        $generation->setPrompt('Test prompt');
        $generation->setTokenCost(1000);

        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($generation);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($generation, 123);

        return $generation;
    }
}
