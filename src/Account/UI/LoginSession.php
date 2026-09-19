<?php
namespace App\Account\UI;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Le peu d'état que la connexion garde entre deux requêtes : l'adresse en cours
 * de validation, et le retour du refus précédent.
 *
 * Rien de tout cela ne décide de la sécurité — les compteurs et les blocages
 * vivent en base (voir `LoginAttempt`). Vider ses cookies fait donc repartir
 * l'écran de zéro, sans rien débloquer.
 */
final readonly class LoginSession
{
    private const string PENDING = 'giulia.login.pending';
    private const string FEEDBACK = 'giulia.login.feedback';

    public function __construct(private RequestStack $requests) {}

    public function open(PendingLogin $pending): void
    {
        $this->requests->getSession()->set(self::PENDING, $pending);
    }

    public function pending(): ?PendingLogin
    {
        $pending = $this->requests->getSession()->get(self::PENDING);

        return $pending instanceof PendingLogin ? $pending : null;
    }

    public function close(): void
    {
        $this->requests->getSession()->remove(self::PENDING);
    }

    /**
     * Repart de zéro : adresse en cours ET retour d'erreur. C'est ce que fait
     * « Changer d'adresse », et le « Réessayer maintenant » de l'écran de
     * blocage — sans quoi cet écran, volontairement persistant, resterait
     * affiché alors que la pause est terminée côté serveur.
     */
    public function forget(): void
    {
        $session = $this->requests->getSession();
        $session->remove(self::PENDING);
        $session->remove(self::FEEDBACK);
    }

    public function push(LoginFeedback $feedback): void
    {
        $this->requests->getSession()->set(self::FEEDBACK, $feedback);
    }

    /** Se lit une seule fois : un message d'erreur ne doit pas survivre à son écran. */
    public function pull(): ?LoginFeedback
    {
        $session = $this->requests->getSession();
        $feedback = $session->get(self::FEEDBACK);
        $session->remove(self::FEEDBACK);

        return $feedback instanceof LoginFeedback ? $feedback : null;
    }
}
