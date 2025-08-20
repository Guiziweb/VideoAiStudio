<?php

declare(strict_types=1);

namespace App\Shared\Entity\Order;

use App\Video\Entity\VideoGeneration;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item')]
class OrderItem extends BaseOrderItem
{
    #[ORM\OneToOne(mappedBy: 'orderItem', targetEntity: VideoGeneration::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?VideoGeneration $videoGeneration = null;

    public function getVideoGeneration(): ?VideoGeneration
    {
        return $this->videoGeneration;
    }

    public function setVideoGeneration(?VideoGeneration $videoGeneration): self
    {
        $this->videoGeneration = $videoGeneration;

        return $this;
    }
}
