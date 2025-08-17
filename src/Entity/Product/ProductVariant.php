<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;
use Sylius\Component\Product\Model\ProductVariantTranslationInterface;
use Sylius\MolliePlugin\Entity\ProductVariantInterface;
use Sylius\MolliePlugin\Entity\RecurringProductVariantTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_variant')]
class ProductVariant extends BaseProductVariant implements ProductVariantInterface
{
    use RecurringProductVariantTrait;

    protected function createTranslation(): ProductVariantTranslationInterface
    {
        return new ProductVariantTranslation();
    }

    public function getShortDescription(): ?string
    {
        /** @var ProductVariantTranslation $translation */
        $translation = $this->getTranslation();

        return $translation->getShortDescription();
    }

    public function setShortDescription(?string $shortDescription): void
    {
        /** @var ProductVariantTranslation $translation */
        $translation = $this->getTranslation();

        $translation->setShortDescription($shortDescription);
    }
}
