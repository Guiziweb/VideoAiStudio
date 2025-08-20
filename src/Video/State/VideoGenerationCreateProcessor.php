<?php

declare(strict_types=1);

namespace App\Video\State;

use App\Shared\Entity\Customer\Customer;
use App\Video\Entity\VideoGeneration;
use Sylius\Component\Core\Context\ShopperContextInterface;
use Sylius\Resource\Context\Context;
use Sylius\Resource\Doctrine\Common\State\PersistProcessor;
use Sylius\Resource\Metadata\Operation;
use Sylius\Resource\State\ProcessorInterface;

final class VideoGenerationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private PersistProcessor $decorated,
        private ShopperContextInterface $shopperContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, Context $context): mixed
    {
        if (!$data instanceof VideoGeneration) {
            return $this->decorated->process($data, $operation, $context);
        }

        $customer = $this->shopperContext->getCustomer();
        if ($customer instanceof Customer) {
            $data->setCustomer($customer);
        }

        $data->setTokenCost(1000);

        return $this->decorated->process($data, $operation, $context);
    }
}
