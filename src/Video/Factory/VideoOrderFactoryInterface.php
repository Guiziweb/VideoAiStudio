<?php

declare(strict_types=1);

namespace App\Video\Factory;

use App\Video\Entity\VideoGeneration;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface VideoOrderFactoryInterface
{
    public function createForVideoGeneration(
        VideoGeneration $videoGeneration,
        ChannelInterface $channel,
        CustomerInterface $customer,
        string $localeCode,
    ): OrderInterface;
}
