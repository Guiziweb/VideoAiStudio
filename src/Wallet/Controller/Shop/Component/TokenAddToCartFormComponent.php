<?php

declare(strict_types=1);

namespace App\Wallet\Controller\Shop\Component;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\CoreBundle\Provider\FlashBagProvider;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Bundle\ShopBundle\Form\Type\AddToCartType;
use Sylius\Bundle\ShopBundle\Twig\Component\Product\Trait\ProductLivePropTrait;
use Sylius\Bundle\ShopBundle\Twig\Component\Product\Trait\ProductVariantLivePropTrait;
use Sylius\Bundle\UiBundle\Twig\Component\TemplatePropTrait;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent(name: 'app:token_add_to_cart_form')]
class TokenAddToCartFormComponent
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use HookableLiveComponentTrait;
    use ProductLivePropTrait;
    use ProductVariantLivePropTrait;
    use TemplatePropTrait;

    #[LiveProp]
    public string $routeName = 'sylius_shop_cart_summary';

    /** @var array<string, mixed> */
    #[LiveProp]
    public array $routeParameters = [];

    public function __construct(
        protected readonly FormFactoryInterface $formFactory,
        protected readonly EntityManagerInterface $orderManager,
        protected readonly RouterInterface $router,
        protected readonly RequestStack $requestStack,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly CartContextInterface $cartContext,
        protected readonly AddToCartCommandFactoryInterface $addToCartCommandFactory,
        protected readonly FactoryInterface $orderItemFactory,
        ProductRepositoryInterface $productRepository,
        ProductVariantRepositoryInterface $productVariantRepository,
    ) {
        $this->initializeProduct($productRepository);
        $this->initializeProductVariant($productVariantRepository);
    }

    #[PostMount(priority: 100)]
    public function postMount(): void
    {
        $this->isValidated = true;
    }

    #[LiveAction]
    public function addToCart(
        #[LiveArg]
        ?string $routeName = null,
        #[LiveArg]
        array $routeParameters = [],
        #[LiveArg]
        bool $addFlashMessage = true,
        #[LiveArg]
        ?int $variantId = null,
    ): RedirectResponse {
        // Si une variante spécifique est demandée, on la configure
        if ($variantId) {
            $variant = $this->productVariantRepository->find($variantId);
            if ($variant && $variant->getProduct() === $this->product) {
                $cartItem = $this->orderItemFactory->createForProduct($this->product);
                $cartItem->setVariant($variant);

                $addToCartCommand = $this->addToCartCommandFactory->createWithCartAndCartItem(
                    $this->cartContext->getCart(),
                    $cartItem,
                );
            } else {
                throw new \InvalidArgumentException('Invalid variant');
            }
        } else {
            $this->submitForm();
            $addToCartCommand = $this->getForm()->getData();
        }

        $this->eventDispatcher->dispatch(new GenericEvent($addToCartCommand), SyliusCartEvents::CART_ITEM_ADD);
        $this->orderManager->persist($addToCartCommand->getCart());
        $this->orderManager->flush();

        if ($addFlashMessage) {
            FlashBagProvider::getFlashBag($this->requestStack)->add('success', 'sylius.cart.add_item');
        }

        return new RedirectResponse($this->router->generate(
            $routeName ?? $this->routeName,
            array_merge($this->routeParameters, $routeParameters),
        ));
    }

    protected function instantiateForm(): FormInterface
    {
        $addToCartCommand = $this->addToCartCommandFactory->createWithCartAndCartItem(
            $this->cartContext->getCart(),
            $this->orderItemFactory->createForProduct($this->product),
        );

        return $this->formFactory->create(AddToCartType::class, $addToCartCommand, ['product' => $this->product]);
    }
}
