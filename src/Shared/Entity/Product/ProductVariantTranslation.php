<?php

declare(strict_types=1);

namespace App\Shared\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Product\Model\ProductVariantTranslation as BaseProductVariantTranslation;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_variant_translation')]
class ProductVariantTranslation extends BaseProductVariantTranslation
{
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }
}
