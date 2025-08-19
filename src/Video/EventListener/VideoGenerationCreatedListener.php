<?php

declare(strict_types=1);

namespace App\Video\EventListener;

use App\Video\Entity\VideoGeneration;
use App\Video\Factory\VideoOrderFactoryInterface;
use App\Video\Processor\VideoPaymentProcessor;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Context\ShopperContextInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'app.video_generation.post_create', method: 'handleVideoGenerationCreate')]
final readonly class VideoGenerationCreatedListener
{
    public function __construct(
        private VideoOrderFactoryInterface $videoOrderFactory,
        private VideoPaymentProcessor $videoPaymentProcessor,
        private ShopperContextInterface $shopperContext,
    ) {
    }

    public function handleVideoGenerationCreate(ResourceControllerEvent $event): void
    {
        $videoGeneration = $event->getSubject();

        if (!$videoGeneration instanceof VideoGeneration) {
            return;
        }

        $customer = $this->shopperContext->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            throw new \RuntimeException('Customer not found');
        }

        $channel = $this->shopperContext->getChannel();
        $localeCode = $this->shopperContext->getLocaleCode();

        $order = $this->videoOrderFactory->createForVideoGeneration(
            $videoGeneration,
            $channel,
            $customer,
            $localeCode,
        );

        $this->videoPaymentProcessor->processPaymentForOrder($order);
    }
}
