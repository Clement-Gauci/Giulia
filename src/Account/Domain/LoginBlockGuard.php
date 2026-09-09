<?php
namespace App\Account\Domain;

/**
 * Décide si une connexion est en pause forcée. Les deux étapes du flux posent la
 * même question, elle vit donc à un seul endroit.
 *
 * Le blocage est glissant : il court BLOCK_MINUTES après la dernière tentative
 * fautive, et se relâche seul quand celles-ci sortent de la fenêtre.
 */
final readonly class LoginBlockGuard
{
    public function __construct(private LoginAttemptRepositoryInterface $attempts) {}

    /**
     * @throws LoginBlocked si l'IP a essuyé trop d'adresses inconnues, ou
     *                      l'adresse trop de codes erronés
     */
    public function refuseIfBlocked(string $email, string $ip, \DateTimeImmutable $now): void
    {
        $window = $now->modify(sprintf('-%d minutes', LoginPolicy::BLOCK_MINUTES));

        $unknown = $this->attempts->tallyForIp(AttemptKind::UnknownEmail, $ip, $window);
        if ($unknown->count >= LoginPolicy::MAX_UNKNOWN_EMAILS && $unknown->lastAt !== null) {
            throw LoginBlocked::after(
                $unknown->lastAt,
                sprintf('%d tentatives avec des adresses non enregistrées.', LoginPolicy::MAX_UNKNOWN_EMAILS),
            );
        }

        $wrong = $this->attempts->tallyForEmail(AttemptKind::WrongCode, $email, $window);
        if ($wrong->count >= LoginPolicy::MAX_CODE_TRIES && $wrong->lastAt !== null) {
            throw LoginBlocked::after(
                $wrong->lastAt,
                sprintf('%d codes erronés de suite.', LoginPolicy::MAX_CODE_TRIES),
            );
        }
    }
}
