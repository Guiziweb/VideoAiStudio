<?php

declare(strict_types=1);

namespace App\Wallet\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @implements ExampleFactoryInterface<ProductInterface> */
final class CustomProductExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        protected readonly FactoryInterface $productFactory,
        protected readonly FactoryInterface $productVariantFactory,
        protected readonly FactoryInterface $channelPricingFactory,
        protected readonly FactoryInterface $productImageFactory,
        protected readonly FactoryInterface $productTaxonFactory,
        protected readonly ImageUploaderInterface $imageUploader,
        protected readonly SlugGeneratorInterface $slugGenerator,
        protected readonly RepositoryInterface $taxonRepository,
        protected readonly RepositoryInterface $productOptionRepository,
        protected readonly RepositoryInterface $channelRepository,
        protected readonly RepositoryInterface $localeRepository,
        protected readonly RepositoryInterface $taxCategoryRepository,
        #[Autowire(service: 'file_locator')]
        protected readonly FileLocatorInterface $fileLocator,
    ) {
        $this->faker = Factory::create();
        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): ProductInterface
    {
        $options = $this->optionsResolver->resolve($options);

        /** @var ProductInterface $product */
        $product = $this->productFactory->createNew();
        $product->setVariantSelectionMethod($options['variant_selection_method']);
        $product->setCode((string) $options['code']);
        $product->setEnabled($options['enabled']);
        $product->setMainTaxon($options['main_taxon']);
        $product->setCreatedAt($this->faker->dateTimeBetween('-1 week'));

        $this->createTranslations($product, $options);
        $this->createRelations($product, $options);
        $this->createVariants($product, $options);
        $this->createImages($product, $options);
        $this->createProductTaxons($product, $options);

        return $product;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('name', fn (Options $options): string => $this->faker->words(3, true))
            ->setDefault('code', fn (Options $options): string => StringInflector::nameToCode($options['name']))
            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')
            ->setDefault('tracked', false)
            ->setAllowedTypes('tracked', 'bool')
            ->setDefault('slug', fn (Options $options): string => $this->slugGenerator->generate($options['name']))
            ->setDefault('short_description', fn (Options $options): string => $this->faker->paragraph)
            ->setDefault('description', fn (Options $options): string => $this->faker->paragraphs(3, true))
            ->setDefault('main_taxon', LazyOption::randomOne($this->taxonRepository))
            ->setAllowedTypes('main_taxon', ['null', 'string', TaxonInterface::class])
            ->setNormalizer('main_taxon', LazyOption::findOneBy($this->taxonRepository, 'code'))
            ->setDefault('taxons', LazyOption::randomOnes($this->taxonRepository, 3))
            ->setAllowedTypes('taxons', 'array')
            ->setNormalizer('taxons', LazyOption::findBy($this->taxonRepository, 'code'))
            ->setDefault('channels', LazyOption::randomOnes($this->channelRepository, 3))
            ->setAllowedTypes('channels', 'array')
            ->setNormalizer('channels', LazyOption::findBy($this->channelRepository, 'code'))
            ->setDefault('variant_selection_method', ProductInterface::VARIANT_SELECTION_MATCH)
            ->setAllowedTypes('variant_selection_method', 'string')
            ->setAllowedValues('variant_selection_method', [ProductInterface::VARIANT_SELECTION_MATCH, ProductInterface::VARIANT_SELECTION_CHOICE])
            ->setRequired('product_options')
            ->setAllowedTypes('product_options', 'array')
            ->setNormalizer('product_options', LazyOption::findBy($this->productOptionRepository, 'code'))
            ->setDefault('images', [])
            ->setAllowedTypes('images', 'array')
            ->setDefault('shipping_required', false)
            ->setDefault('tax_category', null)
            ->setAllowedTypes('tax_category', ['string', 'null', TaxCategoryInterface::class])
            ->setDefault('variants', [])
        ;

        $resolver->setNormalizer('tax_category', LazyOption::findOneBy($this->taxCategoryRepository, 'code'));
    }

    /** @param array<string, mixed> $options */
    private function createTranslations(ProductInterface $product, array $options): void
    {
        foreach ($this->getLocales() as $localeCode) {
            $product->setCurrentLocale($localeCode);
            $product->setFallbackLocale($localeCode);

            $product->setName($options['name']);
            $product->setSlug($options['slug']);
            $product->setShortDescription($options['short_description']);
            $product->setDescription($options['description']);
        }
    }

    /** @param array<string, mixed> $options */
    private function createRelations(ProductInterface $product, array $options): void
    {
        foreach ($options['channels'] as $channel) {
            $product->addChannel($channel);
        }

        foreach ($options['product_options'] as $option) {
            $product->addOption($option);
        }
    }

    /** @param array<string, mixed> $options */
    private function createVariants(ProductInterface $product, array $options): void
    {
        foreach ($options['variants'] as $i => $variantData) {
            /** @var ProductVariantInterface $productVariant */
            $productVariant = $this->productVariantFactory->createNew();
            $productVariant->setCode(sprintf('%s-variant-%d', $options['code'], $i));

            // Utiliser le nom custom ou fallback
            $variantName = $variantData['name'] ?? sprintf('%s Tokens', $variantData['tokens']);
            $productVariant->setName($variantName);

            // Stocker le nombre de tokens dans le champ dédié
            if (isset($variantData['tokens']) && is_numeric($variantData['tokens'])) {
                $productVariant->setTokenAmount((int) $variantData['tokens']);
            }

            // Ajouter la description courte si disponible
            if (isset($variantData['short_description'])) {
                foreach ($this->getLocales() as $localeCode) {
                    $productVariant->setCurrentLocale($localeCode);
                    $productVariant->setFallbackLocale($localeCode);
                    $productVariant->setShortDescription($variantData['short_description']);
                }
            }

            $productVariant->setOnHand($this->faker->randomNumber(1));
            $productVariant->setShippingRequired($options['shipping_required']);
            if (isset($options['tax_category']) && $options['tax_category'] instanceof TaxCategoryInterface) {
                $productVariant->setTaxCategory($options['tax_category']);
            }
            $productVariant->setTracked($options['tracked']);

            // Gérer les options values selon le type de produit
            $this->handleOptionValues($productVariant, $variantData, $options);

            // Créer les prix pour chaque canal
            /** @var ChannelInterface $channel */
            foreach ($this->channelRepository->findAll() as $channel) {
                $this->createCustomChannelPricings($productVariant, $channel->getCode(), $variantData['price']);
            }

            $product->addVariant($productVariant);

            // Ajouter les images spécifiques à ce variant si définies
            if (isset($variantData['images'])) {
                $this->createVariantImages($productVariant, $variantData['images']);
            }
        }
    }

    private function handleOptionValues(ProductVariantInterface $productVariant, array $variantData, array $options): void
    {
        $tokenValue = $variantData['tokens'];

        // Se baser sur les product_options pour déterminer le type de produit
        $productOptions = $options['product_options'];

        foreach ($productOptions as $productOption) {
            $optionCode = $productOption->getCode();

            // Si le produit a l'option 'pack_size', c'est un produit tokens
            if ($optionCode === 'pack_size' && is_numeric($tokenValue)) {
                $optionValueCode = match ($tokenValue) {
                    '10000' => 'tokens_10k',
                    '50000' => 'tokens_50k',
                    '100000' => 'tokens_100k',
                    default => null,
                };

                if ($optionValueCode) {
                    $optionValue = $productOption->getValues()->filter(fn ($v) => $v->getCode() === $optionValueCode)->first();
                    if ($optionValue) {
                        $productVariant->addOptionValue($optionValue);
                    }
                }
            }
            // Si le produit a l'option 'generation_type', c'est un produit vidéo
            elseif ($optionCode === 'generation_type' && $tokenValue === 'standard') {
                $optionValue = $productOption->getValues()->filter(fn ($v) => $v->getCode() === 'standard')->first();
                if ($optionValue) {
                    $productVariant->addOptionValue($optionValue);
                }
            }
        }
    }

    private function createCustomChannelPricings(ProductVariantInterface $productVariant, string $channelCode, int $price): void
    {
        /** @var ChannelPricingInterface $channelPricing */
        $channelPricing = $this->channelPricingFactory->createNew();
        $channelPricing->setChannelCode($channelCode);
        $channelPricing->setPrice($price);

        $productVariant->addChannelPricing($channelPricing);
    }

    /** @param array<array<string, mixed>> $images */
    private function createVariantImages(ProductVariantInterface $productVariant, array $images): void
    {
        foreach ($images as $image) {
            $imagePath = $image['path'];
            $imageType = $image['type'] ?? null;

            $imagePath = $this->fileLocator->locate($imagePath);
            $uploadedImage = new UploadedFile($imagePath, basename($imagePath));

            /** @var ImageInterface $productImage */
            $productImage = $this->productImageFactory->createNew();
            $productImage->setFile($uploadedImage);
            $productImage->setType($imageType);

            $this->imageUploader->upload($productImage);

            // Note: Les images sont associées au produit, pas au variant directement
            // Dans Sylius, les variants partagent les images du produit
            $productVariant->getProduct()->addImage($productImage);
        }
    }

    /** @param array<string, mixed> $options */
    private function createImages(ProductInterface $product, array $options): void
    {
        foreach ($options['images'] as $image) {
            $imagePath = $image['path'];
            $imageType = $image['type'] ?? null;

            $imagePath = $this->fileLocator->locate($imagePath);
            $uploadedImage = new UploadedFile($imagePath, basename($imagePath));

            /** @var ImageInterface $productImage */
            $productImage = $this->productImageFactory->createNew();
            $productImage->setFile($uploadedImage);
            $productImage->setType($imageType);

            $this->imageUploader->upload($productImage);

            $product->addImage($productImage);
        }
    }

    /** @param array<string, mixed> $options */
    private function createProductTaxons(ProductInterface $product, array $options): void
    {
        foreach ($options['taxons'] as $taxon) {
            /** @var ProductTaxonInterface $productTaxon */
            $productTaxon = $this->productTaxonFactory->createNew();
            $productTaxon->setProduct($product);
            $productTaxon->setTaxon($taxon);

            $product->addProductTaxon($productTaxon);
        }
    }

    /** @return iterable<string> */
    private function getLocales(): iterable
    {
        /** @var LocaleInterface[] $locales */
        $locales = $this->localeRepository->findAll();
        foreach ($locales as $locale) {
            yield $locale->getCode();
        }
    }
}
