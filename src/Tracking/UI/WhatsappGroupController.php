<?php
namespace App\Tracking\UI;

use App\Shared\Domain\EstablishmentRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point d'entrée tracké du QR code « antigaspi » affiché en magasin :
 * journalise chaque scan puis redirige vers le groupe WhatsApp.
 */
final class WhatsappGroupController
{
    #[Route('/join-whatsapp-group', name: 'join_whatsapp_group', methods: ['GET'])]
    public function __invoke(
        Request $request,
        EstablishmentRepositoryInterface $establishments,
        LoggerInterface $trackingLogger,
    ): RedirectResponse {
        $trackingLogger->info('Scan QR antigaspi → groupe WhatsApp', [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('Referer'),
            'accept_language' => $request->headers->get('Accept-Language'),
            'query' => $request->query->all(),
        ]);

        return new RedirectResponse($establishments->get()->whatsappUrl());
    }
}
