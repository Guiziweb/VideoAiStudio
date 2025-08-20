<?php

declare(strict_types=1);

namespace App\Shared\Entity\Promotion;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Promotion\Model\PromotionRule as BasePromotionRule;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_promotion_rule')]
class PromotionRule extends BasePromotionRule
{
}
