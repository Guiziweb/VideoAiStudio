<?php

declare(strict_types=1);

namespace App\Shared\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.shop.account', method: 'addVideoMenuItems')]
final class AccountMenuListener
{
    public function addVideoMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $factory = $event->getFactory();

        $existingChildren = $menu->getChildren();

        $videoGeneration = $factory->createItem('video_generation', [
            'route' => 'app_shop_video_generation_create',
        ])
            ->setLabel('app.ui.nav.generate')
            ->setLabelAttribute('icon', 'tabler:sparkles');

        $videoGallery = $factory->createItem('video_gallery', [
            'route' => 'app_shop_video_generation_index',
        ])
            ->setLabel('app.ui.nav.gallery')
            ->setLabelAttribute('icon', 'tabler:video');

        foreach ($existingChildren as $child) {
            $menu->removeChild($child->getName());
        }

        $menu->addChild($videoGeneration);
        $menu->addChild($videoGallery);

        foreach ($existingChildren as $child) {
            $menu->addChild($child);
        }
    }
}
