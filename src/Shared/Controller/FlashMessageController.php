<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FlashMessageController extends AbstractController
{
    #[Route('/api/flash-messages', name: 'app_api_flash_messages', methods: ['GET'])]
    public function getFlashMessages(): JsonResponse
    {
        $session = $this->container->get('request_stack')->getSession();
        $flashBag = $session->getFlashBag();

        $messages = [];
        foreach ($flashBag->all() as $type => $typeMessages) {
            foreach ($typeMessages as $message) {
                $messages[] = [
                    'type' => $type,
                    'message' => $message,
                ];
            }
        }

        return new JsonResponse(['messages' => $messages]);
    }

    #[Route('/flash-messages-partial', name: 'app_flash_messages_partial', methods: ['GET'])]
    public function getFlashMessagesPartial(): Response
    {
        return $this->render('shared/flashes_partial.html.twig');
    }
}
