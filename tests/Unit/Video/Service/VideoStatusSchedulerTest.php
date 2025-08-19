<?php

/*
 * This file is part of the VideoAI Studio package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Unit\Video\Service;

use App\Video\Message\CheckVideoStatusMessage;
use App\Video\Service\VideoStatusScheduler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class VideoStatusSchedulerTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;
    private VideoStatusScheduler $scheduler;
    private int $statusCheckDelay = 30000; // 30 seconds in milliseconds

    protected function setUp(): void
    {
        parent::setUp();

        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->scheduler = new VideoStatusScheduler(
            $this->bus,
            $this->statusCheckDelay
        );
    }

    public function testScheduleDispatchesMessageWithDelayStamp(): void
    {
        $generationId = 123;
        $expectedMessage = new CheckVideoStatusMessage($generationId);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (CheckVideoStatusMessage $message) use ($generationId) {
                    return $message->videoGenerationId === $generationId;
                }),
                $this->callback(function (array $stamps) {
                    $this->assertCount(1, $stamps);
                    $this->assertInstanceOf(DelayStamp::class, $stamps[0]);
                    $this->assertSame($this->statusCheckDelay, $stamps[0]->getDelay());
                    return true;
                })
            )
            ->willReturn(new Envelope($expectedMessage));

        $this->scheduler->schedule($generationId);
    }

    public function testScheduleWithDifferentGenerationIds(): void
    {
        $generationIds = [1, 42, 999, 12345];

        $this->bus
            ->expects($this->exactly(count($generationIds)))
            ->method('dispatch')
            ->willReturnCallback(function (CheckVideoStatusMessage $message, array $stamps) {
                $this->assertInstanceOf(DelayStamp::class, $stamps[0]);
                $this->assertSame($this->statusCheckDelay, $stamps[0]->getDelay());
                return new Envelope($message);
            });

        foreach ($generationIds as $id) {
            $this->scheduler->schedule($id);
        }
    }

    public function testScheduleUsesConfiguredDelay(): void
    {
        $customDelay = 60000; // 1 minute
        $customScheduler = new VideoStatusScheduler($this->bus, $customDelay);
        $generationId = 123;

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CheckVideoStatusMessage::class),
                $this->callback(function (array $stamps) use ($customDelay) {
                    $this->assertCount(1, $stamps);
                    $this->assertInstanceOf(DelayStamp::class, $stamps[0]);
                    $this->assertSame($customDelay, $stamps[0]->getDelay());
                    return true;
                })
            )
            ->willReturn(new Envelope(new CheckVideoStatusMessage($generationId)));

        $customScheduler->schedule($generationId);
    }

    public function testScheduleCreatesCorrectMessageStructure(): void
    {
        $generationId = 456;

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (CheckVideoStatusMessage $message) use ($generationId) {
                    // Verify message structure
                    $this->assertSame($generationId, $message->videoGenerationId);
                    $this->assertIsInt($message->videoGenerationId);
                    return true;
                }),
                $this->isType('array')
            )
            ->willReturn(new Envelope(new CheckVideoStatusMessage($generationId)));

        $this->scheduler->schedule($generationId);
    }
}