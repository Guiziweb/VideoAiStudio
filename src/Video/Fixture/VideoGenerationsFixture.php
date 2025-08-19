<?php

declare(strict_types=1);

namespace App\Video\Fixture;

use App\Entity\Customer\Customer;
use App\Video\Entity\VideoGeneration;
use App\Video\VideoGenerationTransitions;
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
    ) {
    }

    public function load(array $options): void
    {
        /** @var array<array{customer_email: string, prompt: string, status: string, video_url?: string}> $generations */
        $generations = $options['generations'];

        foreach ($generations as $generationData) {
            $customer = $this->customerRepository->findOneBy(['email' => $generationData['customer_email']]);

            if (!$customer instanceof Customer) {
                continue;
            }

            $generation = new VideoGeneration();
            $generation->setCustomer($customer);
            $generation->setPrompt($generationData['prompt']);
            $generation->setWorkflowState($generationData['status']);
            $generation->setTokenCost($generationData['token'] ?? 1000);

            $this->setWorkflowFields($generation, $generationData);

            if (isset($generationData['video_url'])) {
                $generation->setVideoStorageUrl($generationData['video_url']);
            }

            $generation->setCreatedAt(new \DateTime());
            $generation->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($generation);
        }

        $this->entityManager->flush();
    }

    private function setWorkflowFields(VideoGeneration $generation, array $data): void
    {
        $status = $data['status'];

        if (in_array($status, [
            VideoGenerationTransitions::STATE_SUBMITTED,
            VideoGenerationTransitions::STATE_PROCESSING,
            VideoGenerationTransitions::STATE_COMPLETED,
            VideoGenerationTransitions::STATE_FAILED,
        ])) {
            $generation->setExternalProvider($data['external_provider'] ?? 'mock');
            $generation->setExternalJobId($data['external_job_id'] ?? 'fixture_' . uniqid());

            if (isset($data['submitted_at'])) {
                $generation->setExternalSubmittedAt(new \DateTime($data['submitted_at']));
            } else {
                $submittedAt = (new \DateTime())->modify('-' . rand(1, 30) . ' minutes');
                $generation->setExternalSubmittedAt($submittedAt);
            }

            if (isset($data['external_metadata'])) {
                $generation->setExternalMetadata($data['external_metadata']);
            } else {
                // Default metadata
                $generation->setExternalMetadata([
                    'prompt_length' => strlen($generation->getPrompt()),
                    'estimated_duration' => 30,
                    'resolution' => '1920x1080',
                ]);
            }
        }
        if ($status === VideoGenerationTransitions::STATE_FAILED) {
            $generation->setExternalErrorMessage($data['error_message'] ?? 'Simulation error for fixture');
        }
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
                            ->scalarNode('status')->defaultValue(VideoGenerationTransitions::STATE_CREATED)->end()
                            ->scalarNode('video_url')->defaultNull()->end()
                            ->integerNode('token')->defaultValue(1000)->end()
                            ->scalarNode('external_provider')->defaultNull()->end()
                            ->scalarNode('external_job_id')->defaultNull()->end()
                            ->scalarNode('submitted_at')->defaultNull()->end()
                            ->scalarNode('error_message')->defaultNull()->end()
                            ->arrayNode('external_metadata')
                                ->children()
                                    ->scalarNode('prompt_length')->end()
                                    ->integerNode('estimated_duration')->end()
                                    ->scalarNode('resolution')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
