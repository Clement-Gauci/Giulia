<?php
namespace App\Account\Application;

use App\Account\Domain\AccountRepositoryInterface;
use App\Account\Domain\AttemptKind;
use App\Account\Domain\LoginAttempt;
use App\Account\Domain\LoginAttemptRepositoryInterface;
use App\Account\Domain\LoginBlocked;
use App\Account\Domain\LoginBlockGuard;
use App\Account\Domain\LoginCode;
use App\Account\Domain\LoginCodeGenerator;
use App\Account\Domain\LoginCodeMailerInterface;
use App\Account\Domain\LoginCodeRepositoryInterface;
use App\Account\Domain\LoginPolicy;
use App\Account\Domain\ResendTooSoon;
use App\Account\Domain\SendLimitReached;
use App\Account\Domain\UnknownEmail;
use App\Shared\Domain\Clock;

/**
 * Étape 1 de la connexion : émettre un code à 6 chiffres et l'envoyer.
 */
final readonly class RequestLoginCode
{
    private LoginBlockGuard $guard;

    public function __construct(
        private AccountRepositoryInterface $accounts,
        private LoginCodeRepositoryInterface $codes,
        private LoginAttemptRepositoryInterface $attempts,
        private LoginCodeMailerInterface $mailer,
        private LoginCodeGenerator $generator,
        private Clock $clock,
    ) {
        $this->guard = new LoginBlockGuard($attempts);
    }

    /**
     * @throws LoginBlocked     l'adresse ou l'IP est en pause forcée
     * @throws UnknownEmail     adresse non enregistrée ou compte désactivé
     * @throws ResendTooSoon    le délai entre deux envois n'est pas écoulé
     * @throws SendLimitReached plafond d'envois atteint pour le code courant
     */
    public function __invoke(string $email, string $ip): CodeSent
    {
        $email = strtolower(trim($email));
        $now = $this->clock->now();
        $blockWindow = $now->modify(sprintf('-%d minutes', LoginPolicy::BLOCK_MINUTES));

        $this->guard->refuseIfBlocked($email, $ip, $now);

        $account = $this->accounts->findByEmail($email);

        if ($account === null || !$account->isActive()) {
            $this->attempts->record(LoginAttempt::unknownEmail($email, $ip, $now));

            $seen = $this->attempts->tallyForIp(AttemptKind::UnknownEmail, $ip, $blockWindow);
            if ($seen->count >= LoginPolicy::MAX_UNKNOWN_EMAILS) {
                throw LoginBlocked::after($now, sprintf('%d tentatives avec des adresses non enregistrées.', LoginPolicy::MAX_UNKNOWN_EMAILS));
            }

            throw UnknownEmail::withTriesLeft(LoginPolicy::MAX_UNKNOWN_EMAILS - $seen->count);
        }

        $sent = $this->attempts->tallyForEmail(
            AttemptKind::CodeSent,
            $email,
            $now->modify(sprintf('-%d minutes', LoginPolicy::CODE_LIFETIME_MINUTES)),
        );

        if ($sent->lastAt !== null) {
            $availableAt = $sent->lastAt->modify(sprintf('+%d seconds', LoginPolicy::RESEND_DELAY_SECONDS));
            if ($now < $availableAt) {
                throw ResendTooSoon::until($availableAt);
            }
        }

        if ($sent->count >= LoginPolicy::MAX_SENDS_PER_WINDOW) {
            throw SendLimitReached::forWindow();
        }

        $code = $this->generator->generate();
        $loginCode = LoginCode::issue($email, $code, $now, LoginPolicy::CODE_LIFETIME_MINUTES);
        $this->codes->save($loginCode);

        // Tracé AVANT l'envoi : un transport en panne ne doit pas offrir un moyen
        // de contourner le plafond en enchaînant les échecs.
        $this->attempts->record(LoginAttempt::codeSent($email, $ip, $now));

        $this->mailer->sendLoginCode($account, $code, $loginCode->createdAt(), $loginCode->expiresAt());

        return new CodeSent(
            $email,
            $loginCode->expiresAt(),
            $now->modify(sprintf('+%d seconds', LoginPolicy::RESEND_DELAY_SECONDS)),
            LoginPolicy::MAX_SENDS_PER_WINDOW - ($sent->count + 1),
        );
    }
}
