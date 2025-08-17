<?php

declare(strict_types=1);

namespace App\Shared\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.shop.account', method: 'addVideoMenuItems')]
final class AccountMenuListener
{
    public function addVideoMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $factory = $event->getFactory();
        
        // Récupérer les enfants existants
        $existingChildren = $menu->getChildren();
        
        // Créer les nouveaux items vidéo
        $videoGeneration = $factory->createItem('video_generation', [
            'route' => 'app_shop_video_generation_create'
        ])
            ->setLabel('app.ui.nav.generate')
            ->setLabelAttribute('icon', 'tabler:sparkles');
            
        $videoGallery = $factory->createItem('video_gallery', [
            'route' => 'app_shop_video_generation_index'
        ])
            ->setLabel('app.ui.nav.gallery')
            ->setLabelAttribute('icon', 'tabler:video');
        
        // Supprimer tous les enfants existants
        foreach ($existingChildren as $child) {
            $menu->removeChild($child->getName());
        }
        
        // Ajouter d'abord les items vidéo
        $menu->addChild($videoGeneration);
        $menu->addChild($videoGallery);
        
        // Puis remettre les enfants existants
        foreach ($existingChildren as $child) {
            $menu->addChild($child);
        }
    }
}