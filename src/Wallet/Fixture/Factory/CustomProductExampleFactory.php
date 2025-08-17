<?php

declare(strict_types=1);

namespace App\Wallet\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Attribute\AttributeType\SelectAttributeType;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
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
use Sylius\Component\Product\Generator\ProductVariantGeneratorInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/** @implements ExampleFactoryInterface<ProductInterface> */
final class CustomProductExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    protected Generator $faker;

    protected OptionsResolver $optionsResolver;

    public function __construct(
        protected readonly FactoryInterface $productFactory,
        protected readonly FactoryInterface $productVariantFactory,
        protected readonly FactoryInterface $channelPricingFactory,
        protected readonly ProductVariantGeneratorInterface $variantGenerator,
        protected readonly FactoryInterface $productAttributeValueFactory,
        protected readonly FactoryInterface $productImageFactory,
        protected readonly FactoryInterface $productTaxonFactory,
        protected readonly ImageUploaderInterface $imageUploader,
        protected readonly SlugGeneratorInterface $slugGenerator,
        protected readonly RepositoryInterface $taxonRepository,
        protected readonly RepositoryInterface $productAttributeRepository,
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
            ->setDefault('product_attributes', [])
            ->setAllowedTypes('product_attributes', 'array')
            ->setNormalizer('product_attributes', fn (Options $options, array $productAttributes): array => $this->setAttributeValues($productAttributes))
            ->setDefault('product_options', [])
            ->setAllowedTypes('product_options', 'array')
            ->setNormalizer('product_options', LazyOption::findBy($this->productOptionRepository, 'code'))
            ->setDefault('images', [])
            ->setAllowedTypes('images', 'array')
            ->setDefault('shipping_required', false)
            ->setDefault('tax_category', null)
            ->setAllowedTypes('tax_category', ['string', 'null', TaxCategoryInterface::class])
            // Notre option custom pour les variants avec prix
            ->setDefault('variants', [
                [
                    'tokens' => '10000',
                    'price' => 999,
                ],
                [
                    'tokens' => '50000',
                    'price' => 3999,
                ],
                [
                    'tokens' => '100000',
                    'price' => 6999,
                ],
            ])
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

        foreach ($options['product_attributes'] as $attribute) {
            $product->addAttribute($attribute);
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

            // Ajouter l'option value selon le nombre de tokens
            $tokenCount = $variantData['tokens'];

            // Mapper le nombre de tokens vers le code d'option value
            $optionValueCode = match ($tokenCount) {
                '10000' => 'tokens_10k',
                '50000' => 'tokens_50k',
                '100000' => 'tokens_100k',
                default => null,
            };

            if ($optionValueCode) {
                $optionValue = $this->productOptionRepository->findOneBy(['code' => 'pack_size'])
                    ->getValues()->filter(fn ($v) => $v->getCode() === $optionValueCode)->first();
                if ($optionValue) {
                    $productVariant->addOptionValue($optionValue);
                }
            }

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

    private function createChannelPricings(ProductVariantInterface $productVariant, string $channelCode): void
    {
        /** @var ChannelPricingInterface $channelPricing */
        $channelPricing = $this->channelPricingFactory->createNew();
        $channelPricing->setChannelCode($channelCode);
        $channelPricing->setPrice($this->faker->numberBetween(100, 10000));

        $productVariant->addChannelPricing($channelPricing);
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

    /**
     * @param array<string, mixed> $productAttributes
     *
     * @return ProductAttributeValueInterface[]
     */
    private function setAttributeValues(array $productAttributes): array
    {
        $productAttributesValues = [];
        foreach ($productAttributes as $code => $value) {
            /** @var ProductAttributeInterface|null $productAttribute */
            $productAttribute = $this->productAttributeRepository->findOneBy(['code' => $code]);

            Assert::notNull($productAttribute, sprintf('Can not find product attribute with code: "%s"', $code));

            if (!$productAttribute->isTranslatable()) {
                $productAttributesValues[] = $this->configureProductAttributeValue($productAttribute, null, $value);

                continue;
            }

            foreach ($this->getLocales() as $localeCode) {
                $productAttributesValues[] = $this->configureProductAttributeValue($productAttribute, $localeCode, $value);
            }
        }

        return $productAttributesValues;
    }

    private function configureProductAttributeValue(ProductAttributeInterface $productAttribute, ?string $localeCode, mixed $value): ProductAttributeValueInterface
    {
        /** @var ProductAttributeValueInterface $productAttributeValue */
        $productAttributeValue = $this->productAttributeValueFactory->createNew();
        $productAttributeValue->setAttribute($productAttribute);

        if ($value !== null && in_array($productAttribute->getStorageType(), [AttributeValueInterface::STORAGE_DATE, AttributeValueInterface::STORAGE_DATETIME], true)) {
            $value = new \DateTime($value);
        }

        $productAttributeValue->setValue($value ?? $this->getRandomValueForProductAttribute($productAttribute));
        $productAttributeValue->setLocaleCode($localeCode);

        return $productAttributeValue;
    }

    /**
     * @throws \BadMethodCallException
     */
    private function getRandomValueForProductAttribute(ProductAttributeInterface $productAttribute): mixed
    {
        switch ($productAttribute->getStorageType()) {
            case AttributeValueInterface::STORAGE_BOOLEAN:
                return $this->faker->boolean;
            case AttributeValueInterface::STORAGE_INTEGER:
                return $this->faker->numberBetween(0, 10000);
            case AttributeValueInterface::STORAGE_FLOAT:
                return $this->faker->randomFloat(4, 0, 10000);
            case AttributeValueInterface::STORAGE_TEXT:
                return $this->faker->sentence;
            case AttributeValueInterface::STORAGE_DATE:
            case AttributeValueInterface::STORAGE_DATETIME:
                return $this->faker->dateTimeThisCentury;
            case AttributeValueInterface::STORAGE_JSON:
                if ($productAttribute->getType() === SelectAttributeType::TYPE) {
                    if ($productAttribute->getConfiguration()['multiple']) {
                        return $this->faker->randomElements(
                            array_keys($productAttribute->getConfiguration()['choices']),
                            $this->faker->numberBetween(1, count($productAttribute->getConfiguration()['choices'])),
                        );
                    }

                    return [$this->faker->randomKey($productAttribute->getConfiguration()['choices'])];
                }
                // no break
            default:
                throw new \BadMethodCallException();
        }
    }

    private function generateProductVariantName(ProductVariantInterface $variant): string
    {
        return trim(array_reduce(
            $variant->getOptionValues()->toArray(),
            static fn (?string $variantName, ProductOptionValueInterface $variantOption) => $variantName . sprintf('%s ', $variantOption->getValue()),
            '',
        ));
    }
}
