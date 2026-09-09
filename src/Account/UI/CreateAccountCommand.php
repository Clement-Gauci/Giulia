<?php
namespace App\Account\UI;

use App\Account\Application\CreateAccount;
use App\Account\Domain\AccountAlreadyExists;
use App\Account\Domain\AccountMailerException;
use App\Account\Domain\AccountRole;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:account:create',
    description: 'Ouvre un accès au dashboard et en informe la personne par e-mail',
)]
final class CreateAccountCommand extends Command
{
    public function __construct(private readonly CreateAccount $createAccount)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Adresse e-mail de connexion')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nom affiché (« Clément », « Boutique Giulia »…)')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, sprintf('Rôle : %s', implode(' ou ', self::roles())))
            ->addOption('sans-email', null, InputOption::VALUE_NONE, "Créer sans envoyer l'e-mail d'information")
            ->setHelp(<<<'AIDE'
                Ouvre un accès au dashboard.

                  <info>php %command.full_name% --email=clement@example.fr --name=Clément --role=manager</info>

                Un <comment>manager</comment> peut tout faire ; un compte <comment>shop</comment> est
                le compte partagé du poste de la pizzeria, cantonné au quotidien.

                Le premier compte se crée avec <comment>--sans-email</comment>, tant que le
                gabarit d'e-mail n'est pas finalisé.
                AIDE)
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('email')) {
            $input->setOption('email', $io->ask('Adresse e-mail de connexion'));
        }

        if (!$input->getOption('name')) {
            $input->setOption('name', $io->ask('Nom affiché'));
        }

        if (!$input->getOption('role')) {
            $input->setOption('role', $io->choice('Rôle', self::roles(), AccountRole::Manager->value));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $role = AccountRole::tryFrom((string) $input->getOption('role'));

        if ($role === null) {
            $io->error(sprintf('Rôle inconnu « %s ». Valeurs acceptées : %s.', (string) $input->getOption('role'), implode(', ', self::roles())));

            return Command::INVALID;
        }

        $notify = !$input->getOption('sans-email');
        $notified = $notify;

        try {
            $account = ($this->createAccount)(
                (string) $input->getOption('email'),
                (string) $input->getOption('name'),
                $role,
                $notify,
            );
        } catch (AccountAlreadyExists|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (AccountMailerException $e) {
            // Le compte est enregistré : `CreateAccount` sauvegarde avant de
            // notifier, précisément pour qu'un SMTP en panne ne prive personne
            // de son accès. On le dit, et on sort en succès.
            $notified = false;
            $account = null;
            $io->warning(sprintf(
                "Le compte est créé, mais l'e-mail d'information n'a pas pu partir : %s",
                $e->getMessage(),
            ));
        }

        $io->success(sprintf(
            'Accès ouvert pour %s (%s).',
            $account?->email() ?? strtolower(trim((string) $input->getOption('email'))),
            $role->value,
        ));

        if ($notify && $notified) {
            $io->text('Un e-mail d\'information vient de partir.');
        } elseif (!$notify) {
            $io->text('Aucun e-mail envoyé (--sans-email).');
        }

        $io->text('Connexion : la personne demande un code à 6 chiffres depuis /admin/connexion.');

        return Command::SUCCESS;
    }

    /** @return string[] */
    private static function roles(): array
    {
        return array_map(static fn (AccountRole $role): string => $role->value, AccountRole::cases());
    }
}
