<?php

/*
 * This file is part of the VideoAI Studio package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Video\MessageHandler;

use App\Video\Entity\VideoGeneration;
use App\Video\Message\CheckVideoStatusMessage;
use App\Video\MessageHandler\CheckVideoStatusHandler;
use App\Video\Repository\VideoGenerationRepository;
use App\Video\Service\VideoStatusSchedulerInterface;
use App\Video\Service\VideoWorkflowManager;
use App\Video\VideoGenerationTransitions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CheckVideoStatusHandlerTest extends TestCase
{
    private VideoGenerationRepository&MockObject $repository;
    private VideoWorkflowManager&MockObject $workflowManager;
    private VideoStatusSchedulerInterface&MockObject $statusScheduler;
    private LoggerInterface&MockObject $logger;
    private CheckVideoStatusHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(VideoGenerationRepository::class);
        $this->workflowManager = $this->createMock(VideoWorkflowManager::class);
        $this->statusScheduler = $this->createMock(VideoStatusSchedulerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CheckVideoStatusHandler(
            $this->repository,
            $this->workflowManager,
            $this->statusScheduler,
            $this->logger
        );
    }

    public function testHandleUpdatesStatusAndSchedulesNextCheckWhenInProgress(): void
    {
        $generationId = 123;
        $message = new CheckVideoStatusMessage($generationId);
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('external_123');
        $generation->setWorkflowState(VideoGenerationTransitions::STATE_PROCESSING);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($generationId)
            ->willReturn($generation);

        $this->workflowManager
            ->expects($this->once())
            ->method('updateFromProvider')
            ->with($generation)
            ->willReturn(true);

        // Generation remains in progress, should schedule next check
        $this->statusScheduler
            ->expects($this->once())
            ->method('schedule')
            ->with($generationId);

        $this->logger
            ->expects($this->never())
            ->method('info');

        $this->handler->__invoke($message);
    }

    public function testHandleLogsInfoWhenGenerationReachesFinalState(): void
    {
        $generationId = 123;
        $message = new CheckVideoStatusMessage($generationId);
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('external_123');
        $generation->setWorkflowState(VideoGenerationTransitions::STATE_COMPLETED);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($generationId)
            ->willReturn($generation);

        $this->workflowManager
            ->expects($this->once())
            ->method('updateFromProvider')
            ->with($generation)
            ->willReturn(true);

        // Generation is in final state, should not schedule next check
        $this->statusScheduler
            ->expects($this->never())
            ->method('schedule');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Video generation reached final state',
                [
                    'generation_id' => $generationId,
                    'final_state' => VideoGenerationTransitions::STATE_COMPLETED,
                ]
            );

        $this->handler->__invoke($message);
    }

    public function testHandleDoesNothingWhenGenerationNotFound(): void
    {
        $generationId = 123;
        $message = new CheckVideoStatusMessage($generationId);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($generationId)
            ->willReturn(null);

        $this->workflowManager
            ->expects($this->never())
            ->method('updateFromProvider');

        $this->statusScheduler
            ->expects($this->never())
            ->method('schedule');

        $this->handler->__invoke($message);
    }

    public function testHandleDoesNothingWhenNoExternalJobId(): void
    {
        $generationId = 123;
        $message = new CheckVideoStatusMessage($generationId);
        $generation = $this->createVideoGeneration();
        // No external job ID set

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($generationId)
            ->willReturn($generation);

        $this->workflowManager
            ->expects($this->never())
            ->method('updateFromProvider');

        $this->statusScheduler
            ->expects($this->never())
            ->method('schedule');

        $this->handler->__invoke($message);
    }

    public function testHandleSchedulesRetryWhenExceptionOccurs(): void
    {
        $generationId = 123;
        $message = new CheckVideoStatusMessage($generationId);
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('external_123');
        
        $exception = new \RuntimeException('Provider communication failed');

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($generationId)
            ->willReturn($generation);

        $this->workflowManager
            ->expects($this->once())
            ->method('updateFromProvider')
            ->with($generation)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to check video generation status',
                [
                    'generation_id' => $generationId,
                    'error' => 'Provider communication failed',
                ]
            );

        // Should schedule retry on exception
        $this->statusScheduler
            ->expects($this->once())
            ->method('schedule')
            ->with($generationId);

        $this->handler->__invoke($message);
    }

    public function testHandleWithFailedGeneration(): void
    {
        $generationId = 123;
        $message = new CheckVideoStatusMessage($generationId);
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('external_123');
        $generation->setWorkflowState(VideoGenerationTransitions::STATE_FAILED);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($generationId)
            ->willReturn($generation);

        $this->workflowManager
            ->expects($this->once())
            ->method('updateFromProvider')
            ->with($generation)
            ->willReturn(true);

        // Generation is failed (final state), should not schedule next check
        $this->statusScheduler
            ->expects($this->never())
            ->method('schedule');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Video generation reached final state',
                [
                    'generation_id' => $generationId,
                    'final_state' => VideoGenerationTransitions::STATE_FAILED,
                ]
            );

        $this->handler->__invoke($message);
    }

    public function testHandleWithRefundedGeneration(): void
    {
        $generationId = 123;
        $message = new CheckVideoStatusMessage($generationId);
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('external_123');
        $generation->setWorkflowState(VideoGenerationTransitions::STATE_REFUNDED);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($generationId)
            ->willReturn($generation);

        $this->workflowManager
            ->expects($this->once())
            ->method('updateFromProvider')
            ->with($generation)
            ->willReturn(true);

        // Generation is refunded (final state), should not schedule next check
        $this->statusScheduler
            ->expects($this->never())
            ->method('schedule');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Video generation reached final state',
                [
                    'generation_id' => $generationId,
                    'final_state' => VideoGenerationTransitions::STATE_REFUNDED,
                ]
            );

        $this->handler->__invoke($message);
    }

    public function testHandleSchedulesNextCheckForSubmittedState(): void
    {
        $generationId = 123;
        $message = new CheckVideoStatusMessage($generationId);
        $generation = $this->createVideoGeneration();
        $generation->setExternalJobId('external_123');
        $generation->setWorkflowState(VideoGenerationTransitions::STATE_SUBMITTED);

        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($generationId)
            ->willReturn($generation);

        $this->workflowManager
            ->expects($this->once())
            ->method('updateFromProvider')
            ->with($generation)
            ->willReturn(true);

        // Generation is submitted (in progress), should schedule next check
        $this->statusScheduler
            ->expects($this->once())
            ->method('schedule')
            ->with($generationId);

        $this->logger
            ->expects($this->never())
            ->method('info');

        $this->handler->__invoke($message);
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