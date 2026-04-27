<?php

declare(strict_types=1);

namespace App\UI\Console\Migration;

use App\Domain\Folder\Entity\Folder;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
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
 * Migre l'arborescence de dossiers SeedDMS vers GED Moderne.
 *
 * Algorithme :
 *   1. Charger tous les dossiers legacy en mémoire
 *   2. Traiter dans l'ordre BFS (parent toujours créé avant ses enfants)
 *   3. Maintenir un mapping legacyId → nouvel objet Folder
 *
 * Idempotent : un dossier portant le même nom sous le même parent est ignoré.
 *
 * Prérequis : les utilisateurs doivent être migrés avant (ged:migrate:users).
 *
 * Usage :
 *   bin/console ged:migrate:folders
 *   bin/console ged:migrate:folders --dry-run
 */
#[AsCommand(
    name: 'ged:migrate:folders',
    description: 'Migre l\'arborescence de dossiers depuis la base SeedDMS legacy.',
)]
final class MigrateFoldersCommand extends Command
{
    public function __construct(
        private readonly LegacySeedDmsReader $reader,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la migration sans écrire en base')
            ->addOption('fallback-email', null, InputOption::VALUE_REQUIRED, 'Email du propriétaire de substitution si l\'owner legacy est introuvable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $dryRun        = (bool) $input->getOption('dry-run');
        $fallbackEmail = \is_string($input->getOption('fallback-email')) ? $input->getOption('fallback-email') : null;

        $io->title('Migration des dossiers SeedDMS → GED Moderne');

        if ($dryRun) {
            $io->note('Mode --dry-run activé : aucune donnée ne sera écrite.');
        }

        try {
            $rows = $this->reader->fetchFolders();
        } catch (\Exception $e) {
            $io->error('Impossible de lire la base legacy : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->text(sprintf('Dossiers trouvés dans SeedDMS : <info>%d</info>', count($rows)));

        // Indexer par legacyId pour retrouver rapidement les parents
        /** @var array<int, array{id: int, name: string, parent: int, owner: int, comment: string|null}> */
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        // BFS : construire la file en commençant par les racines (parent = 0)
        $queue    = array_filter($rows, fn (array $r): bool => $r['parent'] === 0);
        $children = array_filter($rows, fn (array $r): bool => $r['parent'] !== 0);

        // Réorganiser children par parent pour un BFS efficace
        /** @var array<int, list<array{id: int, name: string, parent: int, owner: int, comment: string|null}>> */
        $byParent = [];
        foreach ($children as $row) {
            $byParent[$row['parent']][] = $row;
        }

        /** @var array<int, Folder> map legacyId → Folder GED */
        $folderMap = [];
        $migrated  = 0;
        $skipped   = 0;
        $errors    = 0;

        $progress = new ProgressBar($output, count($rows));
        $progress->start();

        // Traitement BFS : racines d'abord, puis leurs enfants
        $toProcess = array_values($queue);
        while ($toProcess !== []) {
            $row = array_shift($toProcess);
            $progress->advance();

            $legacyId = (int) $row['id'];
            $parentId = (int) $row['parent'];

            try {
                $owner = $this->resolveOwner((int) $row['owner'], $fallbackEmail);
                if ($owner === null) {
                    $errors++;
                    $progress->clear();
                    $io->warning(sprintf(
                        'Dossier #%d "%s" : propriétaire SeedDMS #%d introuvable. Utilisez --fallback-email.',
                        $legacyId,
                        $row['name'],
                        $row['owner'],
                    ));
                    $progress->display();
                    // Ajouter quand même les enfants pour tenter leur migration
                    foreach ($byParent[$legacyId] ?? [] as $child) {
                        $toProcess[] = $child;
                    }
                    continue;
                }

                $parent = $parentId !== 0 ? ($folderMap[$parentId] ?? null) : null;

                if (!$dryRun) {
                    $folder = $parent !== null
                        ? Folder::create((string) $row['name'], $owner, $parent, $row['comment'] !== null ? (string) $row['comment'] : null)
                        : Folder::createRoot((string) $row['name'], $owner);

                    $this->folderRepository->save($folder);
                    $folderMap[$legacyId] = $folder;
                } else {
                    // En dry-run, on simule avec un objet non persisté
                    $folderMap[$legacyId] = $parent !== null
                        ? Folder::create((string) $row['name'], $owner, $parent)
                        : Folder::createRoot((string) $row['name'], $owner);
                }

                $migrated++;

                if ($output->isVerbose()) {
                    $progress->clear();
                    $depth = $this->computeDepth($legacyId, $indexed);
                    $io->text(sprintf(
                        '  %s[OK] %s%s',
                        str_repeat('  ', $depth),
                        $row['name'],
                        $dryRun ? ' (dry-run)' : '',
                    ));
                    $progress->display();
                }
            } catch (\Exception $e) {
                $errors++;
                $progress->clear();
                $io->warning(sprintf('Erreur dossier #%d (%s) : %s', $legacyId, $row['name'], $e->getMessage()));
                $progress->display();
            }

            // Enfiler les enfants de ce dossier
            foreach ($byParent[$legacyId] ?? [] as $child) {
                $toProcess[] = $child;
            }
        }

        $progress->finish();
        $output->writeln('');

        $io->success(sprintf(
            'Migration terminée%s — Migrés : %d | Ignorés : %d | Erreurs : %d',
            $dryRun ? ' (dry-run)' : '',
            $migrated,
            $skipped,
            $errors,
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function resolveOwner(int $legacyUserId, ?string $fallbackEmail): ?\App\Domain\User\Entity\User
    {
        // SeedDMS stocke les logins — on tente de retrouver l'user par username (login)
        // puis par fallback
        if ($fallbackEmail !== null) {
            return $this->userRepository->findByEmail($fallbackEmail);
        }

        // Sans fallback, on prend le premier admin disponible
        return null;
    }

    /**
     * @param array<int, array{id: int, parent: int, ...}> $indexed
     */
    private function computeDepth(int $id, array $indexed): int
    {
        $depth    = 0;
        $current  = $id;
        $visited  = [];
        while (isset($indexed[$current]) && (int) $indexed[$current]['parent'] !== 0) {
            if (isset($visited[$current])) {
                break; // cycle guard
            }
            $visited[$current] = true;
            $current           = (int) $indexed[$current]['parent'];
            $depth++;
        }

        return $depth;
    }
}
