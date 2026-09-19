<?php
namespace App\Account\Application;

use App\Account\Domain\Account;
use App\Account\Domain\AccountAlreadyExists;
use App\Account\Domain\AccountMailerInterface;
use App\Account\Domain\AccountRepositoryInterface;
use App\Shared\Domain\Clock;

final readonly class CreateAccount
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private AccountMailerInterface $mailer,
        private Clock $clock,
    ) {}

    /**
     * @throws AccountAlreadyExists si l'adresse est déjà prise
     * @throws \App\Account\Domain\AccountMailerException si la notification échoue —
     *         le compte est alors DÉJÀ enregistré : un accès ne doit pas dépendre
     *         de la remise SMTP. C'est à l'appelant de le signaler.
     */
    public function __invoke(string $email, string $name, bool $notify): Account
    {
        $account = Account::create($email, $name, $this->clock->now());

        if ($this->accounts->exists($account->email())) {
            throw AccountAlreadyExists::withEmail($account->email());
        }

        $this->accounts->save($account);

        if ($notify) {
            $this->mailer->sendAccountCreated($account);
        }

        return $account;
    }
}
