<?php
namespace App\Account\Application;

use App\Account\Domain\Account;
use App\Account\Domain\AccountRepositoryInterface;
use App\Account\Domain\AttemptKind;
use App\Account\Domain\LoginAttempt;
use App\Account\Domain\LoginAttemptRepositoryInterface;
use App\Account\Domain\LoginBlocked;
use App\Account\Domain\LoginBlockGuard;
use App\Account\Domain\LoginCodeRepositoryInterface;
use App\Account\Domain\LoginPolicy;
use App\Account\Domain\NoActiveCode;
use App\Account\Domain\WrongCode;
use App\Shared\Domain\Clock;

/**
 * Étape 2 de la connexion : vérifier le code saisi.
 */
final readonly class VerifyLoginCode
{
    private LoginBlockGuard $guard;

    public function __construct(
        private AccountRepositoryInterface $accounts,
        private LoginCodeRepositoryInterface $codes,
        private LoginAttemptRepositoryInterface $attempts,
        private Clock $clock,
    ) {
        $this->guard = new LoginBlockGuard($attempts);
    }

    /**
     * @throws LoginBlocked  l'adresse ou l'IP est en pause forcée
     * @throws NoActiveCode  aucun code utilisable pour cette adresse
     * @throws WrongCode     code erroné, essais restants indiqués
     */
    public function __invoke(string $email, string $candidate, string $ip): Account
    {
        $email = strtolower(trim($email));
        $now = $this->clock->now();

        $this->guard->refuseIfBlocked($email, $ip, $now);

        $code = $this->codes->findFor($email);
        $account = $this->accounts->findByEmail($email);

        if ($code === null || $code->isConsumed() || $code->isExpired($now) || $account === null || !$account->isActive()) {
            throw NoActiveCode::forEmail();
        }

        if (!$code->matches($candidate)) {
            $this->codes->save($code->withFailedTry());
            $this->attempts->record(LoginAttempt::wrongCode($email, $ip, $now));

            $wrong = $this->attempts->tallyForEmail(
                AttemptKind::WrongCode,
                $email,
                $now->modify(sprintf('-%d minutes', LoginPolicy::BLOCK_MINUTES)),
            );

            if ($wrong->count >= LoginPolicy::MAX_CODE_TRIES) {
                throw LoginBlocked::after($now, sprintf('%d codes erronés de suite.', LoginPolicy::MAX_CODE_TRIES));
            }

            throw WrongCode::withTriesLeft(LoginPolicy::MAX_CODE_TRIES - $wrong->count);
        }

        $this->codes->save($code->consume($now));

        $connected = $account->withLastLoginAt($now);
        $this->accounts->save($connected);

        return $connected;
    }
}
