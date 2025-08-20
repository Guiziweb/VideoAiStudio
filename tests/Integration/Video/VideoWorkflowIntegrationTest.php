<?php

/*
 * This file is part of the VideoAI Studio package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Integration\Video;

use App\IA\Provider\MockProvider;
use App\Video\Entity\VideoGeneration;
use App\Video\Service\VideoWorkflowManager;
use App\Video\VideoGenerationTransitions;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class VideoWorkflowIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WorkflowInterface $workflow;
    private VideoWorkflowManager $workflowManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->workflow = self::getContainer()->get('state_machine.video_generation');
        $this->workflowManager = self::getContainer()->get(VideoWorkflowManager::class);

        // Clean database before each test to ensure isolation
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
        $this->entityManager->clear();
    }

    public function testCompleteWorkflowFromCreatedToCompleted(): void
    {
        $generation = $this->createPersistedVideoGeneration();

        // Initial state
        $this->assertTrue($generation->isCreated());
        $this->assertSame(VideoGenerationTransitions::STATE_CREATED, $generation->getWorkflowState());

        // Submit to provider
        $result = $this->workflowManager->submitToProvider($generation);
        $this->assertTrue($result);
        $this->assertTrue($generation->isSubmitted());
        $this->assertNotNull($generation->getExternalProvider());
        $this->assertNotNull($generation->getExternalJobId());
        $this->assertNotNull($generation->getExternalSubmittedAt());

        // Mark as processing
        $result = $this->workflowManager->markAsProcessing($generation);
        $this->assertTrue($result);
        $this->assertTrue($generation->isProcessing());

        // Mark as completed
        $result = $this->workflowManager->markAsCompleted($generation);
        $this->assertTrue($result);
        $this->assertTrue($generation->isCompleted());
        $this->assertNotNull($generation->getVideoStorageUrl());
        $this->assertTrue($generation->isFinalState());
        $this->assertFalse($generation->isInProgress());
    }

    public function testWorkflowFailureScenario(): void
    {
        $generation = $this->createPersistedVideoGeneration();

        // Submit to provider
        $this->workflowManager->submitToProvider($generation);
        $this->assertTrue($generation->isSubmitted());

        // Mark as failed from submitted state
        $result = $this->workflowManager->markAsFailed($generation, 'Provider timeout');
        $this->assertTrue($result);
        $this->assertTrue($generation->isFailed());
        $this->assertSame('Provider timeout', $generation->getExternalErrorMessage());
        $this->assertTrue($generation->isFinalState());
        $this->assertFalse($generation->isInProgress());
    }

    public function testWorkflowFailureFromProcessingState(): void
    {
        $generation = $this->createPersistedVideoGeneration();

        // Go through submit -> processing
        $this->workflowManager->submitToProvider($generation);
        $this->workflowManager->markAsProcessing($generation);
        $this->assertTrue($generation->isProcessing());

        // Mark as failed from processing state
        $result = $this->workflowManager->markAsFailed($generation, 'Rendering error');
        $this->assertTrue($result);
        $this->assertTrue($generation->isFailed());
        $this->assertSame('Rendering error', $generation->getExternalErrorMessage());
    }

    #[DataProvider('invalidTransitionsProvider')]
    public function testInvalidTransitionsAreBlocked(string $fromState, string $transition, bool $expectedResult): void
    {
        $generation = $this->createPersistedVideoGeneration();
        $generation->setWorkflowState($fromState);
        $this->entityManager->flush();

        $canTransition = $this->workflow->can($generation, $transition);
        $this->assertSame($expectedResult, $canTransition);

        if (!$expectedResult) {
            // Try to apply anyway - should fail
            $result = match ($transition) {
                'submit' => $this->workflowManager->submitToProvider($generation),
                'start_processing' => $this->workflowManager->markAsProcessing($generation),
                'complete' => $this->workflowManager->markAsCompleted($generation),
                'fail' => $this->workflowManager->markAsFailed($generation, 'Test'),
                default => false,
            };
            $this->assertFalse($result);
        }
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function invalidTransitionsProvider(): iterable
    {
        // Valid transitions
        yield 'created to submit - valid' => [VideoGenerationTransitions::STATE_CREATED, 'submit', true];
        yield 'submitted to start_processing - valid' => [VideoGenerationTransitions::STATE_SUBMITTED, 'start_processing', true];
        yield 'processing to complete - valid' => [VideoGenerationTransitions::STATE_PROCESSING, 'complete', true];
        yield 'submitted to fail - valid' => [VideoGenerationTransitions::STATE_SUBMITTED, 'fail', true];
        yield 'processing to fail - valid' => [VideoGenerationTransitions::STATE_PROCESSING, 'fail', true];

        // Invalid transitions
        yield 'submitted to complete - invalid' => [VideoGenerationTransitions::STATE_SUBMITTED, 'complete', false];
        yield 'created to start_processing - invalid' => [VideoGenerationTransitions::STATE_CREATED, 'start_processing', false];
        yield 'completed to submit - invalid' => [VideoGenerationTransitions::STATE_COMPLETED, 'submit', false];
        yield 'completed to fail - invalid' => [VideoGenerationTransitions::STATE_COMPLETED, 'fail', false];
        yield 'failed to complete - invalid' => [VideoGenerationTransitions::STATE_FAILED, 'complete', false];
    }

    public function testProviderIntegrationWithMockProvider(): void
    {
        $generation = $this->createPersistedVideoGeneration();

        // Submit with mock provider
        $result = $this->workflowManager->submitToProvider($generation);
        $this->assertTrue($result);
        $this->assertSame('mock', $generation->getExternalProvider());
        $this->assertStringStartsWith('mock_', $generation->getExternalJobId());
        $this->assertIsArray($generation->getExternalMetadata());
        $this->assertArrayHasKey('prompt_length', $generation->getExternalMetadata());
    }

    public function testUpdateFromProviderWithDifferentStatuses(): void
    {
        $generation = $this->createPersistedVideoGeneration();

        // Submit first
        $this->workflowManager->submitToProvider($generation);
        $this->assertTrue($generation->isSubmitted());

        // Mock that provider now says it's processing
        $mockProvider = self::getContainer()->get(MockProvider::class);

        // We need to simulate time passing for MockProvider's time-based logic
        // Set external submitted time to past
        $pastTime = new \DateTime('-15 seconds');
        $generation->setExternalSubmittedAt($pastTime);
        $this->entityManager->flush();

        // Update from provider should move to processing
        $result = $this->workflowManager->updateFromProvider($generation);
        $this->assertTrue($result);
        $this->assertTrue($generation->isProcessing());

        // Set time further in past to simulate completion
        $olderTime = new \DateTime('-35 seconds');
        $generation->setExternalSubmittedAt($olderTime);
        $this->entityManager->flush();

        // Update from provider should move to completed
        $result = $this->workflowManager->updateFromProvider($generation);
        $this->assertTrue($result);
        $this->assertTrue($generation->isCompleted());
        $this->assertNotNull($generation->getVideoStorageUrl());
        $this->assertStringContainsString('mock-s3-bucket.com', $generation->getVideoStorageUrl());
    }

    public function testProviderFailureScenario(): void
    {
        $generation = $this->createPersistedVideoGeneration();
        $generation->setPrompt('Test prompt with error keyword'); // MockProvider fails on "error"

        $this->workflowManager->submitToProvider($generation);
        $this->assertTrue($generation->isSubmitted());

        // Update from provider should detect failure
        $result = $this->workflowManager->updateFromProvider($generation);
        $this->assertTrue($result);
        $this->assertTrue($generation->isFailed());
    }

    public function testWorkflowStatusHelperMethods(): void
    {
        $generation = $this->createPersistedVideoGeneration();

        // Test status helper methods for each state
        $states = [
            VideoGenerationTransitions::STATE_CREATED => 'isCreated',
            VideoGenerationTransitions::STATE_SUBMITTED => 'isSubmitted',
            VideoGenerationTransitions::STATE_PROCESSING => 'isProcessing',
            VideoGenerationTransitions::STATE_COMPLETED => 'isCompleted',
            VideoGenerationTransitions::STATE_FAILED => 'isFailed',
            VideoGenerationTransitions::STATE_REFUNDED => 'isRefunded',
        ];

        foreach ($states as $state => $method) {
            $generation->setWorkflowState($state);

            // Only the current state method should return true
            foreach ($states as $otherState => $otherMethod) {
                $expected = $state === $otherState;
                $this->assertSame($expected, $generation->$otherMethod(),
                    "Method {$otherMethod} should return {$expected} when state is {$state}"
                );
            }

            // Test isFinalState and isInProgress
            $isFinal = in_array($state, [
                VideoGenerationTransitions::STATE_COMPLETED,
                VideoGenerationTransitions::STATE_FAILED,
                VideoGenerationTransitions::STATE_REFUNDED,
            ]);
            $isInProgress = in_array($state, [
                VideoGenerationTransitions::STATE_SUBMITTED,
                VideoGenerationTransitions::STATE_PROCESSING,
            ]);

            $this->assertSame($isFinal, $generation->isFinalState(),
                "isFinalState should return {$isFinal} for state {$state}"
            );
            $this->assertSame($isInProgress, $generation->isInProgress(),
                "isInProgress should return {$isInProgress} for state {$state}"
            );
        }
    }

    private function createPersistedVideoGeneration(): VideoGeneration
    {
        $generation = new VideoGeneration();
        $generation->setPrompt('Test video generation prompt');
        $generation->setTokenCost(1000);

        // We need to create a mock customer and order item for persistence
        // For integration tests, we'll create real entities
        $customer = new \App\Shared\Entity\Customer\Customer();
        $customer->setEmail('test_' . uniqid() . '@example.com');
        $customer->setFirstName('Test');
        $customer->setLastName('User');

        $this->entityManager->persist($customer);

        // Create a product and product variant for the order item
        $product = new \App\Shared\Entity\Product\Product();
        $product->setCode('TEST_PRODUCT_' . uniqid());

        $productVariant = new \App\Shared\Entity\Product\ProductVariant();
        $productVariant->setCode('TEST_VARIANT_' . uniqid());
        $productVariant->setProduct($product);
        $product->addVariant($productVariant);

        $this->entityManager->persist($product);
        $this->entityManager->persist($productVariant);

        // Create a minimal order and order item for the relationship
        $order = new \App\Shared\Entity\Order\Order();
        $order->setCurrencyCode('EUR');
        $order->setLocaleCode('en_US');
        $order->setCustomer($customer);

        $orderItem = new \App\Shared\Entity\Order\OrderItem();
        $orderItem->setOrder($order);
        $orderItem->setVariant($productVariant);
        $orderItem->setUnitPrice(1000);

        $this->entityManager->persist($order);
        $this->entityManager->persist($orderItem);

        $generation->setCustomer($customer);
        $generation->setOrderItem($orderItem);

        $this->entityManager->persist($generation);
        $this->entityManager->flush();

        return $generation;
    }

    protected function tearDown(): void
    {
        // Clean up database after each test using Doctrine's ORMPurger
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
        $this->entityManager->clear();

        parent::tearDown();
    }
}
