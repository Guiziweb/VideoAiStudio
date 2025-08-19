<?php

/*
 * This file is part of the VideoAI Studio package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Video\Service;

use App\Video\Entity\VideoGeneration;
use App\Video\Gateway\VideoProviderGatewayInterface;
use App\Video\Service\VideoStatusSchedulerInterface;
use App\Video\Service\VideoWorkflowManager;
use App\Video\VideoGenerationTransitions;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class VideoWorkflowManagerTest extends TestCase
{
    private WorkflowInterface&MockObject $workflow;

    private EntityManagerInterface&MockObject $entityManager;

    private VideoProviderGatewayInterface&MockObject $providerService;

    private VideoStatusSchedulerInterface&MockObject $statusScheduler;

    private VideoWorkflowManager $workflowManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = $this->createMock(WorkflowInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->providerService = $this->createMock(VideoProviderGatewayInterface::class);
        $this->statusScheduler = $this->createMock(VideoStatusSchedulerInterface::class);

        $this->workflowManager = new VideoWorkflowManager(
            $this->workflow,
            $this->entityManager,
            $this->providerService,
            $this->statusScheduler,
        );
    }

    public function testItSubmitsJobToProviderSuccessfully(): void
    {
        $generation = $this->createVideoGeneration();

        $providerResult = [
            'provider' => 'mock',
            'job_id' => 'mock_123',
            'metadata' => ['test' => 'data'],
        ];

        $this->providerService
            ->expects($this->once())
            ->method('submitJob')
            ->with($generation)
            ->willReturn($providerResult)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($generation, 'submit')
            ->willReturn(true)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('apply')
            ->with($generation, 'submit')
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $this->statusScheduler
            ->expects($this->once())
            ->method('schedule')
            ->with($generation->getId())
        ;

        $result = $this->workflowManager->submitToProvider($generation);

        $this->assertTrue($result);
        $this->assertSame('mock', $generation->getExternalProvider());
        $this->assertSame('mock_123', $generation->getExternalJobId());
        $this->assertNotNull($generation->getExternalSubmittedAt());
        $this->assertSame(['test' => 'data'], $generation->getExternalMetadata());
    }

    public function testItFailsToSubmitWhenProviderFails(): void
    {
        $generation = $this->createVideoGeneration();

        $this->providerService
            ->expects($this->once())
            ->method('submitJob')
            ->with($generation)
            ->willReturn(null)
        ;

        $this->workflow
            ->expects($this->never())
            ->method('can')
        ;

        $this->statusScheduler
            ->expects($this->never())
            ->method('schedule')
        ;

        $result = $this->workflowManager->submitToProvider($generation);

        $this->assertFalse($result);
    }

    public function testItFailsToSubmitWhenTransitionNotAllowed(): void
    {
        $generation = $this->createVideoGeneration();

        $providerResult = [
            'provider' => 'mock',
            'job_id' => 'mock_123',
        ];

        $this->providerService
            ->expects($this->once())
            ->method('submitJob')
            ->with($generation)
            ->willReturn($providerResult)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($generation, 'submit')
            ->willReturn(false)
        ;

        $this->workflow
            ->expects($this->never())
            ->method('apply')
        ;

        $this->statusScheduler
            ->expects($this->never())
            ->method('schedule')
        ;

        $result = $this->workflowManager->submitToProvider($generation);

        $this->assertFalse($result);
    }

    #[DataProvider('updateFromProviderDataProvider')]
    public function testItUpdatesFromProviderWithDifferentStatuses(
        string $currentState,
        string $providerStatus,
        ?string $expectedTransition,
        bool $expectedResult,
    ): void {
        $generation = $this->createVideoGeneration();
        $generation->setWorkflowState($currentState);

        $this->providerService
            ->expects($this->once())
            ->method('getJobStatus')
            ->with($generation)
            ->willReturn($providerStatus)
        ;

        if ($expectedTransition) {
            $this->workflow
                ->expects($this->once())
                ->method('can')
                ->with($generation, $expectedTransition)
                ->willReturn(true)
            ;

            $this->workflow
                ->expects($this->once())
                ->method('apply')
                ->with($generation, $expectedTransition)
            ;

            $this->entityManager
                ->expects($this->once())
                ->method('flush')
            ;
        } else {
            $this->workflow
                ->expects($this->never())
                ->method('can')
            ;
        }

        $result = $this->workflowManager->updateFromProvider($generation);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function updateFromProviderDataProvider(): iterable
    {
        yield 'submitted to processing' => [
            'currentState' => VideoGenerationTransitions::STATE_SUBMITTED,
            'providerStatus' => VideoGenerationTransitions::STATE_PROCESSING,
            'expectedTransition' => 'start_processing',
            'expectedResult' => true,
        ];

        yield 'processing to completed' => [
            'currentState' => VideoGenerationTransitions::STATE_PROCESSING,
            'providerStatus' => VideoGenerationTransitions::STATE_COMPLETED,
            'expectedTransition' => 'complete',
            'expectedResult' => true,
        ];

        yield 'submitted to failed' => [
            'currentState' => VideoGenerationTransitions::STATE_SUBMITTED,
            'providerStatus' => VideoGenerationTransitions::STATE_FAILED,
            'expectedTransition' => 'fail',
            'expectedResult' => true,
        ];

        yield 'processing to failed' => [
            'currentState' => VideoGenerationTransitions::STATE_PROCESSING,
            'providerStatus' => VideoGenerationTransitions::STATE_FAILED,
            'expectedTransition' => 'fail',
            'expectedResult' => true,
        ];

        yield 'invalid transition submitted to completed' => [
            'currentState' => VideoGenerationTransitions::STATE_SUBMITTED,
            'providerStatus' => VideoGenerationTransitions::STATE_COMPLETED,
            'expectedTransition' => null,
            'expectedResult' => false,
        ];

        yield 'invalid transition completed to processing' => [
            'currentState' => VideoGenerationTransitions::STATE_COMPLETED,
            'providerStatus' => VideoGenerationTransitions::STATE_PROCESSING,
            'expectedTransition' => null,
            'expectedResult' => false,
        ];
    }

    public function testItUpdatesFromProviderFailsWhenNoStatus(): void
    {
        $generation = $this->createVideoGeneration();

        $this->providerService
            ->expects($this->once())
            ->method('getJobStatus')
            ->with($generation)
            ->willReturn(null)
        ;

        $this->workflow
            ->expects($this->never())
            ->method('can')
        ;

        $result = $this->workflowManager->updateFromProvider($generation);

        $this->assertFalse($result);
    }

    public function testItMarksAsProcessing(): void
    {
        $generation = $this->createVideoGeneration();

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($generation, 'start_processing')
            ->willReturn(true)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('apply')
            ->with($generation, 'start_processing')
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $result = $this->workflowManager->markAsProcessing($generation);

        $this->assertTrue($result);
    }

    public function testItMarksAsCompletedWithVideoUrl(): void
    {
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('mock_123');

        $jobResult = [
            'video_url' => 'https://example.com/video.mp4',
        ];

        $this->providerService
            ->expects($this->once())
            ->method('getJobResult')
            ->with('mock_123')
            ->willReturn($jobResult)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($generation, 'complete')
            ->willReturn(true)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('apply')
            ->with($generation, 'complete')
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $result = $this->workflowManager->markAsCompleted($generation);

        $this->assertTrue($result);
        $this->assertSame('https://example.com/video.mp4', $generation->getVideoStorageUrl());
    }

    public function testItMarksAsCompletedWithoutVideoUrl(): void
    {
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('mock_123');

        $this->providerService
            ->expects($this->once())
            ->method('getJobResult')
            ->with('mock_123')
            ->willReturn(null)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($generation, 'complete')
            ->willReturn(true)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('apply')
            ->with($generation, 'complete')
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $result = $this->workflowManager->markAsCompleted($generation);

        $this->assertTrue($result);
        $this->assertNull($generation->getVideoStorageUrl());
    }

    public function testItMarksAsFailed(): void
    {
        $generation = $this->createVideoGeneration();

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($generation, 'fail')
            ->willReturn(true)
        ;

        $this->workflow
            ->expects($this->once())
            ->method('apply')
            ->with($generation, 'fail')
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        $result = $this->workflowManager->markAsFailed($generation, 'Test error');

        $this->assertTrue($result);
        $this->assertSame('Test error', $generation->getExternalErrorMessage());
    }

    public function testItFailsToApplyTransitionWhenNotAllowed(): void
    {
        $generation = $this->createVideoGeneration();

        $this->workflow
            ->expects($this->once())
            ->method('can')
            ->with($generation, 'fail')
            ->willReturn(false)
        ;

        $this->workflow
            ->expects($this->never())
            ->method('apply')
        ;

        $this->entityManager
            ->expects($this->never())
            ->method('flush')
        ;

        $result = $this->workflowManager->markAsFailed($generation, 'Test error');

        $this->assertFalse($result);
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
