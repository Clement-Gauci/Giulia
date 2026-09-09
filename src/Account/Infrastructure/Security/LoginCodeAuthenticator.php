<?php
namespace App\Account\Infrastructure\Security;

use App\Account\Application\VerifyLoginCode;
use App\Account\Domain\LoginRefused;
use App\Account\UI\LoginFeedback;
use App\Account\UI\LoginSession;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Étape 2 de la connexion. Symfony n'a pas d'authenticator natif pour un code à
 * 6 chiffres, et le pare-feu en exige un de toute façon (voir la spec) : celui-ci
 * délègue la vérification au domaine et se contente de traduire le résultat.
 *
 * Il sert aussi de point d'entrée : un visiteur anonyme qui vise /admin est
 * renvoyé vers l'écran de connexion.
 */
final class LoginCodeAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly VerifyLoginCode $verify,
        private readonly LoginSession $session,
        private readonly UrlGeneratorInterface $urls,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && $request->attributes->get('_route') === 'admin_login_code';
    }

    public function authenticate(Request $request): Passport
    {
        $pending = $this->session->pending();

        if ($pending === null) {
            throw new AuthenticationException('Votre demande de code a expiré. Reprenez depuis le début.');
        }

        try {
            $account = ($this->verify)(
                $pending->email,
                self::readCode($request),
                $request->getClientIp() ?? '0.0.0.0',
            );
        } catch (LoginRefused $refusal) {
            throw new LoginRefusedException($refusal);
        }

        return new SelfValidatingPassport(
            new UserBadge($account->email()),
            [
                new CsrfTokenBadge('authenticate', $request->request->getString('_csrf_token')),
                // Le badge est présent, mais c'est CheckRememberMeConditionsListener
                // qui l'active — seulement si le formulaire porte `_remember_me`.
                new RememberMeBadge(),
            ],
        );
    }

    public function onAuthenticationSuccess(Request $request, mixed $token, string $firewallName): ?Response
    {
        $this->session->close();

        return new RedirectResponse($this->urls->generate('admin_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $feedback = $exception instanceof LoginRefusedException
            ? LoginFeedback::fromRefusal($exception->refusal())
            : LoginFeedback::message($exception->getMessage());

        $this->session->push($feedback);

        // Redirection plutôt que rendu direct : le contrôleur reste le seul à
        // savoir peindre un écran, et un rafraîchissement ne rejoue pas le POST.
        return new RedirectResponse($this->urls->generate(
            $this->session->pending() === null ? 'admin_login' : 'admin_login_code',
        ));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urls->generate('admin_login'));
    }

    /** Le formulaire envoie six cases séparées ; sans JavaScript, un seul champ. */
    private static function readCode(Request $request): string
    {
        $raw = $request->request->all()['code'] ?? '';
        $joined = is_array($raw) ? implode('', array_map(strval(...), $raw)) : (string) $raw;

        return preg_replace('/\D/', '', $joined) ?? '';
    }
}
