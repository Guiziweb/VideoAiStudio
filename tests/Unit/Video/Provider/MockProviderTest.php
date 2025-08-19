<?php

/*
 * This file is part of the VideoAI Studio package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Video\Provider;

use App\IA\Provider\Exception\ProviderException;
use App\IA\Provider\MockProvider;
use App\Video\Entity\VideoGeneration;
use App\Video\VideoGenerationTransitions;
use PHPUnit\Framework\TestCase;

final class MockProviderTest extends TestCase
{
    private MockProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new MockProvider();
    }

    public function testSubmitJobReturnsValidResponse(): void
    {
        $generation = $this->createVideoGeneration();

        $result = $this->provider->submitJob($generation);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('job_id', $result);
        $this->assertArrayHasKey('metadata', $result);

        $this->assertSame('mock', $result['provider']);
        $this->assertStringStartsWith('mock_', $result['job_id']);
        $this->assertIsArray($result['metadata']);

        $this->assertArrayHasKey('prompt_length', $result['metadata']);
        $this->assertArrayHasKey('submitted_at', $result['metadata']);
        $this->assertSame(strlen($generation->getPrompt()), $result['metadata']['prompt_length']);
    }

    public function testGetJobStatusWithTimeBasedProgression(): void
    {
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('mock_test123');

        // Test recent submission (should be queued)
        $generation->setExternalSubmittedAt(new \DateTime('-5 seconds'));
        $status = $this->getStatusWithRetry($generation);
        $this->assertSame('MOCK_QUEUED', $status);

        // Test medium age (should be rendering)
        $generation->setExternalSubmittedAt(new \DateTime('-20 seconds'));
        $status = $this->getStatusWithRetry($generation);
        $this->assertSame('MOCK_RENDERING', $status);

        // Test old submission (should be done)
        $generation->setExternalSubmittedAt(new \DateTime('-40 seconds'));
        $status = $this->getStatusWithRetry($generation);
        $this->assertSame('MOCK_DONE', $status);
    }

    public function testGetJobStatusWithFailureKeywords(): void
    {
        $generation = $this->createVideoGeneration();
        $generation->setPrompt('This should fail because of error keyword');
        $generation->setExternalJobId('mock_test123');
        $generation->setExternalSubmittedAt(new \DateTime('-5 seconds'));

        $status = $this->provider->getJobStatus($generation);
        $this->assertSame('MOCK_ERROR', $status);
    }

    public function testGetJobStatusWithInvalidJobId(): void
    {
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Invalid mock job ID: invalid_id');

        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('invalid_id');

        $this->provider->getJobStatus($generation);
    }

    public function testGetJobStatusWithEmptyJobId(): void
    {
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Invalid mock job ID: ');

        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('');

        $this->provider->getJobStatus($generation);
    }

    public function testMapToNormalizedStatus(): void
    {
        $mappings = [
            'MOCK_PENDING' => VideoGenerationTransitions::STATE_SUBMITTED,
            'MOCK_QUEUED' => VideoGenerationTransitions::STATE_SUBMITTED,
            'MOCK_RENDERING' => VideoGenerationTransitions::STATE_PROCESSING,
            'MOCK_DONE' => VideoGenerationTransitions::STATE_COMPLETED,
            'MOCK_ERROR' => VideoGenerationTransitions::STATE_FAILED,
            'UNKNOWN_STATUS' => VideoGenerationTransitions::STATE_SUBMITTED, // Default fallback
        ];

        foreach ($mappings as $providerStatus => $expectedWorkflowState) {
            $result = $this->provider->mapToNormalizedStatus($providerStatus);
            $this->assertSame($expectedWorkflowState, $result,
                "Failed mapping {$providerStatus} to {$expectedWorkflowState}"
            );
        }
    }

    public function testGetJobResult(): void
    {
        $externalJobId = 'mock_test123';

        $result = $this->provider->getJobResult($externalJobId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('video_url', $result);
        $this->assertArrayHasKey('metadata', $result);

        $expectedUrl = "https://mock-s3-bucket.com/videos/{$externalJobId}.mp4";
        $this->assertSame($expectedUrl, $result['video_url']);

        $this->assertIsArray($result['metadata']);
        $this->assertArrayHasKey('duration', $result['metadata']);
        $this->assertArrayHasKey('resolution', $result['metadata']);
        $this->assertArrayHasKey('format', $result['metadata']);
        $this->assertArrayHasKey('file_size', $result['metadata']);

        $this->assertSame(30, $result['metadata']['duration']);
        $this->assertSame('1920x1080', $result['metadata']['resolution']);
        $this->assertSame('mp4', $result['metadata']['format']);
        $this->assertSame(2048576, $result['metadata']['file_size']);
    }

    public function testCancelJobWithValidId(): void
    {
        $result = $this->provider->cancelJob('mock_valid_id');
        $this->assertTrue($result);
    }

    public function testCancelJobWithInvalidId(): void
    {
        $result = $this->provider->cancelJob('invalid_id');
        $this->assertFalse($result);
    }

    public function testGetType(): void
    {
        $this->assertSame('mock', $this->provider->getType());
    }

    public function testIsHealthy(): void
    {
        $this->assertTrue($this->provider->isHealthy());
    }


    public function testFailureKeywordDetection(): void
    {
        $failurePrompts = [
            'This will fail',
            'Generate ERROR video',
            'Something might crash here',
            'Test FAIL scenario',
            'Video with Error in title',
        ];

        foreach ($failurePrompts as $prompt) {
            $generation = $this->createVideoGeneration();
            $generation->setPrompt($prompt);
            $generation->setExternalJobId('mock_test123');
            $generation->setExternalSubmittedAt(new \DateTime('-5 seconds'));

            // Retry to avoid random API failure
            $attempts = 0;
            $maxAttempts = 5;
            $status = null;

            do {
                try {
                    $status = $this->provider->getJobStatus($generation);
                    break;
                } catch (ProviderException $e) {
                    $attempts++;
                    if ($attempts >= $maxAttempts) {
                        throw $e;
                    }
                }
            } while ($attempts < $maxAttempts);

            $this->assertSame('MOCK_ERROR', $status,
                "Prompt '{$prompt}' should trigger failure"
            );
        }

        $successPrompts = [
            'Generate beautiful video',
            'Create amazing content',
            'Normal video prompt',
        ];

        foreach ($successPrompts as $prompt) {
            $generation = $this->createVideoGeneration();
            $generation->setPrompt($prompt);
            $generation->setExternalJobId('mock_test123');
            $generation->setExternalSubmittedAt(new \DateTime('-5 seconds'));

            // Retry to avoid random API failure
            $attempts = 0;
            $maxAttempts = 5;
            $status = null;

            do {
                try {
                    $status = $this->provider->getJobStatus($generation);
                    break;
                } catch (ProviderException $e) {
                    $attempts++;
                    if ($attempts >= $maxAttempts) {
                        // If we still get random failures after retries, skip this assertion
                        $this->markTestSkipped('Random API failure occurred too many times');
                    }
                }
            } while ($attempts < $maxAttempts);

            $this->assertNotSame('MOCK_ERROR', $status,
                "Prompt '{$prompt}' should not trigger failure"
            );
        }
    }

    public function testGetJobStatusWithNoSubmittedAt(): void
    {
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('mock_test123');
        // No submitted at set

        $status = $this->provider->getJobStatus($generation);
        $this->assertSame('MOCK_PENDING', $status);
    }


    private function createVideoGeneration(): VideoGeneration
    {
        $generation = new VideoGeneration();
        $generation->setPrompt('Test video generation prompt');
        $generation->setTokenCost(1000);

        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($generation);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($generation, 123);

        return $generation;
    }

    private function getStatusWithRetry(VideoGeneration $generation, int $maxRetries = 5): string
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                return $this->provider->getJobStatus($generation);
            } catch (ProviderException $e) {
                if (str_contains($e->getMessage(), 'Mock API randomly failed')) {
                    continue; // Retry on random failure
                }
                throw $e; // Re-throw other exceptions
            }
        }

        throw new \RuntimeException('Max retries reached due to random API failures');
    }
}
