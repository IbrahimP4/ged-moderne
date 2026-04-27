<?php

declare(strict_types=1);

namespace App\UI\Console\Migration;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Migration\LegacySeedDmsReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Migre les utilisateurs SeedDMS vers GED Moderne.
 *
 * Idempotent : un utilisateur déjà existant (même email) est ignoré.
 * Le hash de mot de passe est transféré tel quel — l'utilisateur devra
 * réinitialiser son mot de passe si les algorithmes diffèrent.
 *
 * Usage :
 *   bin/console ged:migrate:users
 *   bin/console ged:migrate:users --dry-run
 */
#[AsCommand(
    name: 'ged:migrate:users',
    description: 'Migre les utilisateurs depuis la base SeedDMS legacy.',
)]
final class MigrateUsersCommand extends Command
{
    // SeedDMS role constants
    private const ROLE_ADMIN = 1;

    public function __construct(
        private readonly LegacySeedDmsReader $reader,
        private readonly UserRepositoryInterface $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la migration sans écrire en base')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Nombre d\'entités par flush', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Migration des utilisateurs SeedDMS → GED Moderne');

        if ($dryRun) {
            $io->note('Mode --dry-run activé : aucune donnée ne sera écrite.');
        }

        try {
            $total = $this->reader->countUsers();
            $users = $this->reader->fetchUsers();
        } catch (\Exception $e) {
            $io->error('Impossible de lire la base legacy : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->text(sprintf('Utilisateurs trouvés dans SeedDMS : <info>%d</info>', $total));

        $progress  = new ProgressBar($output, $total);
        $migrated  = 0;
        $skipped   = 0;
        $errors    = 0;

        $progress->start();

        foreach ($users as $row) {
            $progress->advance();

            try {
                $existing = $this->userRepository->findByEmail((string) $row['email']);
                if ($existing !== null) {
                    $skipped++;
                    continue;
                }

                $username = $this->sanitizeUsername((string) $row['login']);
                if ($this->userRepository->findByUsername($username) !== null) {
                    $username = $username . '_' . $row['id'];
                }

                if (!$dryRun) {
                    $user = User::create(
                        username: $username,
                        email: (string) $row['email'],
                        hashedPassword: (string) $row['pwd'],
                        isAdmin: ((int) $row['role']) === self::ROLE_ADMIN,
                    );

                    $this->userRepository->save($user);
                }

                $migrated++;

                if ($output->isVerbose()) {
                    $progress->clear();
                    $io->text(sprintf(
                        '  [OK] %s <%s>%s',
                        $row['login'],
                        $row['email'],
                        $dryRun ? ' (dry-run)' : '',
                    ));
                    $progress->display();
                }
            } catch (\Exception $e) {
                $errors++;
                $progress->clear();
                $io->warning(sprintf('Erreur pour l\'utilisateur #%d (%s) : %s', $row['id'], $row['login'], $e->getMessage()));
                $progress->display();
            }
        }

        $progress->finish();
        $output->writeln('');

        $io->success(sprintf(
            'Migration terminée%s — Migrés : %d | Ignorés (déjà présents) : %d | Erreurs : %d',
            $dryRun ? ' (dry-run)' : '',
            $migrated,
            $skipped,
            $errors,
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function sanitizeUsername(string $login): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $login);

        return $sanitized !== null && $sanitized !== '' ? $sanitized : 'user_' . uniqid();
    }
}
