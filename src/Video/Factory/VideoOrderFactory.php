<?php

declare(strict_types=1);

namespace App\Video\Factory;

use App\Shared\Entity\Product\Product;
use App\Video\Entity\VideoGeneration;
use App\Video\Enum\ProductCode;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final readonly class VideoOrderFactory implements VideoOrderFactoryInterface
{
    public function __construct(
        private FactoryInterface $orderFactory,
        private OrderModifierInterface $orderModifier,
        private FactoryInterface $orderItemFactory,
        private FactoryInterface $addressFactory,
        private ProductRepositoryInterface $productRepository,
        private OrderRepositoryInterface $orderRepository,
        private EntityManagerInterface $cartManager,
    ) {
    }

    public function createForVideoGeneration(
        VideoGeneration $videoGeneration,
        ChannelInterface $channel,
        CustomerInterface $customer,
        string $localeCode,
    ): OrderInterface {
        /** @var OrderInterface $order */
        $order = $this->orderFactory->createNew();

        $order->setChannel($channel);

        $baseCurrency = $channel->getBaseCurrency();

        if ($baseCurrency === null) {
            throw new \RuntimeException('Currency not found');
        }

        $order->setCurrencyCode($baseCurrency->getCode());
        $order->setLocaleCode($localeCode);
        $order->setCustomer($customer);

        // TDDO:: dont hack address
        $billingAddress = $this->getOrCreateAddressForCustomer($customer, $channel);
        $shippingAddress = $this->getOrCreateAddressForCustomer($customer, $channel);
        $order->setBillingAddress($billingAddress);
        $order->setShippingAddress($shippingAddress);

        $product = $this->productRepository->findOneBy(['code' => ProductCode::VIDEO_GENERATION->value]);

        if (!$product instanceof Product) {
            throw new \RuntimeException('Video generation product not found');
        }

        $orderItem = $this->orderItemFactory->createForProduct($product);

        $orderItem->setVideoGeneration($videoGeneration);

        $videoGeneration->setOrderItem($orderItem);

        $this->orderModifier->addToOrder($order, $orderItem);

        $this->cartManager->persist($order);
        $this->cartManager->flush();

        return $order;
    }

    private function getOrCreateAddressForCustomer(CustomerInterface $customer, ChannelInterface $channel): AddressInterface
    {
        // Try to get the last address from customer's previous orders
        $lastOrder = $this->orderRepository->createQueryBuilder('o')
            ->where('o.customer = :customer')
            ->andWhere('o.billingAddress IS NOT NULL')
            ->orderBy('o.createdAt', 'DESC')
            ->setParameter('customer', $customer)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastOrder && $lastOrder->getBillingAddress()) {
            $existingAddress = $lastOrder->getBillingAddress();

            // Create a new address with the same data instead of cloning
            /** @var AddressInterface $newAddress */
            $newAddress = $this->addressFactory->createNew();
            $newAddress->setFirstName($existingAddress->getFirstName() ?: 'Digital');
            $newAddress->setLastName($existingAddress->getLastName() ?: 'Customer');
            $newAddress->setStreet($existingAddress->getStreet() ?: 'Digital Product');
            $newAddress->setCity($existingAddress->getCity() ?: 'Digital');
            $newAddress->setPostcode($existingAddress->getPostcode() ?: '00000');
            $newAddress->setCountryCode($existingAddress->getCountryCode() ?: 'FR');

            return $newAddress;
        }

        // Fallback: create a virtual address if no previous address found
        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setFirstName('Digital');
        $address->setLastName('Customer');
        $address->setStreet('Digital Product');
        $address->setCity('Digital');
        $address->setPostcode('00000');
        $address->setCountryCode('FR');

        // Persist the address before returning it
        $this->cartManager->persist($address);
        $this->cartManager->flush();

        return $address;
    }
}
