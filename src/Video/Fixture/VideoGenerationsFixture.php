<?php

declare(strict_types=1);

namespace App\Video\Fixture;

use App\Entity\Customer\Customer;
use App\Video\Entity\VideoGeneration;
use App\Video\Service\VideoGenerationCostCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class VideoGenerationsFixture extends AbstractFixture
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        /** @var CustomerRepositoryInterface<Customer> */
        private CustomerRepositoryInterface $customerRepository,
        private VideoGenerationCostCalculator $costCalculator,
    ) {
    }

    public function load(array $options): void
    {
        /** @var array<array{customer_email: string, prompt: string, status: string, video_url?: string}> $generations */
        $generations = $options['generations'];

        // Récupérer le prix du produit VIDEO_GENERATION
        $tokenCost = $this->costCalculator->getGenerationCost();

        foreach ($generations as $generationData) {
            $customer = $this->customerRepository->findOneBy(['email' => $generationData['customer_email']]);

            if (!$customer instanceof Customer) {
                continue;
            }

            $generation = new VideoGeneration();
            $generation->setCustomer($customer);
            $generation->setPrompt($generationData['prompt']);
            $generation->setStatus($generationData['status']);
            $generation->setTokenCost($tokenCost);

            if (isset($generationData['video_url'])) {
                $generation->setVideoUrl($generationData['video_url']);
            }

            $generation->setCreatedAt(new \DateTime());
            $generation->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($generation);
        }

        $this->entityManager->flush();
    }

    public function getName(): string
    {
        return 'video_generations';
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('generations')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('customer_email')->isRequired()->end()
                            ->scalarNode('prompt')->isRequired()->end()
                            ->scalarNode('status')->defaultValue('pending')->end()
                            ->scalarNode('video_url')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
