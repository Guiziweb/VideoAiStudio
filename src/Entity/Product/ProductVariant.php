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

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $tokenAmount = null;

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

    public function getTokenAmount(): ?int
    {
        return $this->tokenAmount;
    }

    public function setTokenAmount(?int $tokenAmount): self
    {
        $this->tokenAmount = $tokenAmount;

        return $this;
    }
}
