<?php

declare(strict_types=1);

namespace App\Wallet\Fixture;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Bundle\FixturesBundle\Fixture\FixtureInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TokenProductFixture extends AbstractFixture implements FixtureInterface
{
    public function __construct(
        private readonly ObjectManager $productManager,
        #[Autowire(service: 'App\Wallet\Fixture\Factory\CustomProductExampleFactory')]
        private readonly ExampleFactoryInterface $tokenProductExampleFactory,
    ) {
    }

    public function load(array $options): void
    {
        $tokenProduct = $this->tokenProductExampleFactory->create($options);

        $this->productManager->persist($tokenProduct);
        $this->productManager->flush();
    }

    public function getName(): string
    {
        return 'wallet_token_product';
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->scalarNode('name')->end()
                ->scalarNode('code')->end()
                ->booleanNode('enabled')->end()
                ->scalarNode('description')->end()
                ->scalarNode('short_description')->end()
                ->scalarNode('main_taxon')->end()
                ->arrayNode('taxons')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('channels')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('product_options')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('images')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('path')->end()
                            ->scalarNode('type')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('variants')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')->end()
                            ->scalarNode('short_description')->end()
                            ->scalarNode('tokens')->end()
                            ->integerNode('price')->end()
                            ->arrayNode('images')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('path')->end()
                                        ->scalarNode('type')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
