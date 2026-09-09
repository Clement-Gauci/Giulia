<?php
namespace App\Account\UI;

use App\Account\Application\RequestLoginCode;
use App\Account\Domain\LoginBlocked;
use App\Account\Domain\LoginRefused;
use App\Shared\Domain\Clock;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Les écrans de connexion au dashboard. La vérification du code, elle, est
 * portée par `LoginCodeAuthenticator` : elle doit passer par le pare-feu.
 */
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly RequestLoginCode $requestCode,
        private readonly LoginSession $session,
        private readonly Clock $clock,
    ) {}

    #[Route('/admin/connexion', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if ($request->isMethod('POST')) {
            return $this->askForCode($request);
        }

        if ($this->session->pending() !== null) {
            return $this->redirectToRoute('admin_login_code');
        }

        return $this->renderStep('admin/login/_step_email.html.twig');
    }

    #[Route('/admin/connexion/code', name: 'admin_login_code', methods: ['GET', 'POST'])]
    public function code(): Response
    {
        // Le POST de cette route est intercepté par LoginCodeAuthenticator : s'il
        // arrive jusqu'ici, c'est qu'il n'a pas été traité, on renvoie l'écran.
        $pending = $this->session->pending();

        if ($pending === null) {
            return $this->redirectToRoute('admin_login');
        }

        return $this->renderStep('admin/login/_step_code.html.twig', ['pending' => $pending]);
    }

    #[Route('/admin/connexion/renvoi', name: 'admin_login_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('authenticate', $request->request->getString('_csrf_token'))) {
            $this->session->push(LoginFeedback::message('Formulaire expiré, réessayez.'));

            return $this->redirectToRoute('admin_login_code');
        }

        $pending = $this->session->pending();

        if ($pending === null) {
            return $this->redirectToRoute('admin_login');
        }

        return $this->issue($pending->email, $pending->remember, $request);
    }

    #[Route('/admin/connexion/changer', name: 'admin_login_change', methods: ['GET'])]
    public function change(): Response
    {
        $this->session->forget();

        return $this->redirectToRoute('admin_login');
    }

    #[Route('/admin/deconnexion', name: 'admin_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Interceptée par le pare-feu (voir security.yaml).');
    }

    private function askForCode(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('authenticate', $request->request->getString('_csrf_token'))) {
            $this->session->push(LoginFeedback::message('Formulaire expiré, réessayez.'));

            return $this->redirectToRoute('admin_login');
        }

        return $this->issue(
            $request->request->getString('email'),
            $request->request->getBoolean('_remember_me'),
            $request,
        );
    }

    private function issue(string $email, bool $remember, Request $request): Response
    {
        try {
            $sent = ($this->requestCode)($email, $request->getClientIp() ?? '0.0.0.0');
        } catch (LoginRefused $refusal) {
            $this->session->push(LoginFeedback::fromRefusal($refusal));

            if ($refusal instanceof LoginBlocked) {
                $this->session->close();

                return $this->redirectToRoute('admin_login');
            }

            return $this->redirectToRoute($this->session->pending() === null ? 'admin_login' : 'admin_login_code');
        }

        $this->session->open(PendingLogin::from($sent, $remember));

        return $this->redirectToRoute('admin_login_code');
    }

    /** @param array<string, mixed> $context */
    private function renderStep(string $step, array $context = []): Response
    {
        $feedback = $this->session->pull();
        $now = $this->clock->now();

        if ($feedback?->isBlocking() && $feedback->secondsLeft($now) > 0) {
            $step = 'admin/login/_blocked.html.twig';
            // Un message d'erreur ne survit pas à son écran, mais un blocage si :
            // sans ça, un simple rafraîchissement ferait disparaître le compte à
            // rebours alors que la pause court toujours côté serveur.
            $this->session->push($feedback);
        }

        return $this->render('admin/login/index.html.twig', $context + [
            'step' => $step,
            'feedback' => $feedback,
            'now' => $now,
        ]);
    }
}
