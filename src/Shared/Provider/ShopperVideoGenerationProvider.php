<?php

declare(strict_types=1);

namespace App\Shared\Provider;

use App\Entity\Customer\Customer;
use App\Video\Entity\VideoGeneration;
use App\Video\Repository\VideoGenerationRepository;
use Sylius\Component\Core\Context\ShopperContextInterface;
use Sylius\Resource\Context\Context;
use Sylius\Resource\Metadata\Operation;
use Sylius\Resource\State\ProviderInterface;

final class ShopperVideoGenerationProvider implements ProviderInterface
{
    public function __construct(
        private VideoGenerationRepository $repository,
        private ShopperContextInterface $shopperContext,
    ) {
    }

    /**
     * @return array<int, VideoGeneration>
     */
    public function provide(Operation $operation, Context $context): array
    {
        $customer = $this->shopperContext->getCustomer();

        if (!$customer instanceof Customer) {
            return [];
        }

        return $this->repository->findByCustomer($customer);
    }
}
