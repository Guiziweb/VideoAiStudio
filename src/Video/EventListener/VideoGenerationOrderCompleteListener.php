<?php

declare(strict_types=1);

namespace App\Video\EventListener;

use App\Video\Service\VideoWorkflowManager;
use App\Video\VideoGenerationTransitions;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsEventListener(event: 'workflow.sylius_order_checkout.completed.complete', method: 'onOrderComplete')]
final readonly class VideoGenerationOrderCompleteListener
{
    public function __construct(
        #[Autowire(service: 'state_machine.video_generation')]
        private WorkflowInterface $workflow,
        private VideoWorkflowManager $workflowManager,
    ) {
    }

    public function onOrderComplete(CompletedEvent $event): void
    {
        $order = $event->getSubject();

        if (!$order instanceof OrderInterface) {
            return;
        }

        foreach ($order->getItems() as $orderItem) {
            $videoGeneration = $orderItem->getVideoGeneration();

            if ($videoGeneration && $this->workflow->can($videoGeneration, VideoGenerationTransitions::TRANSITION_SUBMIT)) {
                $this->workflowManager->submitToProvider($videoGeneration);
            }
        }
    }
}
