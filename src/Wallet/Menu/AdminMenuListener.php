<?php

declare(strict_types=1);

namespace App\Wallet\Menu;

use Knp\Menu\ItemInterface;
use Knp\Menu\MenuItem;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main', method: 'addAdminMenuItems')]
final class AdminMenuListener
{
    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $this->createWalletMenu($menu);
    }

    private function createWalletMenu(ItemInterface $menu): void
    {
        $existingChildrenNames = array_keys($menu->getChildren());

        $menu->addChild('wallet')
            ->setLabel('app.ui.wallet')
            ->setLabelAttribute('icon', 'tabler:wallet')
            ->setExtra('always_open', true);

        $item = $menu->getChild('wallet');
        if (!$item instanceof MenuItem) {
            return;
        }

        // Sous-menu
        $item
            ->addChild('wallets', ['route' => 'app_admin_wallet_index'])
            ->setLabel('app.ui.wallet')
            ->setLabelAttribute('icon', 'tabler:wallet');

        $item
            ->addChild('transactions', ['route' => 'app_admin_wallet_transaction_index'])
            ->setLabel('app.ui.wallet_transactions')
            ->setLabelAttribute('icon', 'tabler:history');
    }
}
