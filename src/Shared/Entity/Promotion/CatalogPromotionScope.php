<?php

declare(strict_types=1);

namespace App\Shared\Entity\Promotion;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\CatalogPromotionScope as BaseCatalogPromotionScope;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_catalog_promotion_scope')]
class CatalogPromotionScope extends BaseCatalogPromotionScope
{
}
