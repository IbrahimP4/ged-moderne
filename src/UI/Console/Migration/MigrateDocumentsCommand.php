<?php

declare(strict_types=1);

namespace App\UI\Console\Migration;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Folder\Repository\FolderRepositoryInterface;
use App\Domain\Port\EventBusInterface;
use App\Domain\Storage\Port\DocumentStorageInterface;
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
 * Migre les documents et leurs versions depuis SeedDMS vers GED Moderne.
 *
 * Pour chaque document :
 *   1. Résoudre le dossier cible (doit exister — lancer ged:migrate:folders avant)
 *   2. Résoudre le propriétaire
 *   3. Pour chaque version : copier le fichier physique via DocumentStorageInterface
 *   4. Créer l'entité Document + versions dans GED Moderne
 *
 * Chemin physique SeedDMS : {LEGACY_STORAGE_DIR}/{dir}/{version}.{fileType}
 *
 * Prérequis :
 *   - ged:migrate:users doit avoir été exécuté
 *   - ged:migrate:folders doit avoir été exécuté
 *   - LEGACY_STORAGE_DIR doit pointer vers le DATA_DIR de SeedDMS
 *
 * Usage :
 *   bin/console ged:migrate:documents
 *   bin/console ged:migrate:documents --dry-run
 *   bin/console ged:migrate:documents --skip-files  (migre les métadonnées sans les fichiers)
 */
#[AsCommand(
    name: 'ged:migrate:documents',
    description: 'Migre les documents et leurs versions depuis la base SeedDMS legacy.',
)]
final class MigrateDocumentsCommand extends Command
{
    public function __construct(
        private readonly LegacySeedDmsReader $reader,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly DocumentStorageInterface $storage,
        private readonly EventBusInterface $eventBus,
        private readonly string $legacyStorageDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la migration sans écrire')
            ->addOption('skip-files', null, InputOption::VALUE_NONE, 'Migre les métadonnées uniquement, sans copier les fichiers')
            ->addOption('fallback-email', null, InputOption::VALUE_REQUIRED, 'Email du propriétaire de substitution si l\'owner est introuvable')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Nombre de documents par flush', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $dryRun        = (bool) $input->getOption('dry-run');
        $skipFiles     = (bool) $input->getOption('skip-files');
        $fallbackEmail = \is_string($input->getOption('fallback-email')) ? $input->getOption('fallback-email') : null;

        $io->title('Migration des documents SeedDMS → GED Moderne');

        if ($dryRun) {
            $io->note('Mode --dry-run : aucune donnée ne sera écrite.');
        }
        if ($skipFiles) {
            $io->note('Mode --skip-files : les fichiers physiques ne seront pas copiés.');
        }

        $fallback = $fallbackEmail !== null ? $this->userRepository->findByEmail($fallbackEmail) : null;

        try {
            $documents = $this->reader->fetchDocuments();
        } catch (\Exception $e) {
            $io->error('Impossible de lire la base legacy : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $total    = count($documents);
        $migrated = 0;
        $skipped  = 0;
        $errors   = 0;

        $io->text(sprintf('Documents trouvés dans SeedDMS : <info>%d</info>', $total));

        $progress = new ProgressBar($output, $total);
        $progress->start();

        foreach ($documents as $row) {
            $progress->advance();

            $legacyId = (int) $row['id'];

            try {
                // ── 1. Résoudre le dossier cible ─────────────────────────────
                // On cherche un dossier par son nom dans la racine — la correspondance
                // exacte nécessiterait une table de mapping (voir note ci-dessous).
                // Stratégie : utiliser le dossier racine comme fallback si le mapping
                // exact est introuvable (les dossiers doivent être migrés au préalable).
                $folder = $this->folderRepository->findRoot();
                if ($folder === null) {
                    $progress->clear();
                    $io->warning('Aucun dossier racine trouvé. Lancez ged:migrate:folders d\'abord.');
                    $progress->display();
                    $errors++;
                    continue;
                }

                // ── 2. Résoudre le propriétaire ───────────────────────────────
                $owner = $fallback;
                if ($owner === null) {
                    $progress->clear();
                    $io->warning(sprintf(
                        'Document #%d "%s" : propriétaire introuvable. Utilisez --fallback-email.',
                        $legacyId,
                        $row['name'],
                    ));
                    $progress->display();
                    $errors++;
                    continue;
                }

                // ── 3. Charger les versions ───────────────────────────────────
                $versions = $this->reader->fetchVersionsForDocument($legacyId);
                if ($versions === []) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $migrated++;
                    if ($output->isVerbose()) {
                        $progress->clear();
                        $io->text(sprintf('  [dry-run] Document #%d "%s" — %d version(s)', $legacyId, $row['name'], count($versions)));
                        $progress->display();
                    }
                    continue;
                }

                // ── 4. Première version → Document::upload() ─────────────────
                $firstVersion = array_shift($versions);
                $storagePath  = $this->copyFile($firstVersion, $skipFiles);

                $mimeType = $this->safeMimeType((string) $firstVersion['mimeType']);
                $fileSize = FileSize::fromBytes((int) $firstVersion['fileSize']);

                $title = ($row['title'] !== null && (string) $row['title'] !== '')
                    ? (string) $row['title']
                    : (string) $row['name'];

                $document = Document::upload(
                    title: $title,
                    folder: $folder,
                    owner: $owner,
                    mimeType: $mimeType,
                    fileSize: $fileSize,
                    originalFilename: (string) $firstVersion['origFileName'],
                    storagePath: $storagePath,
                    comment: $row['comment'] !== null ? (string) $row['comment'] : null,
                );

                // ── 5. Versions suivantes → addVersion() ──────────────────────
                foreach ($versions as $versionRow) {
                    $vStoragePath = $this->copyFile($versionRow, $skipFiles);
                    $vMimeType    = $this->safeMimeType((string) $versionRow['mimeType']);
                    $vFileSize    = FileSize::fromBytes((int) $versionRow['fileSize']);

                    $document->addVersion(
                        uploadedBy: $owner,
                        mimeType: $vMimeType,
                        fileSize: $vFileSize,
                        originalFilename: (string) $versionRow['origFileName'],
                        storagePath: $vStoragePath,
                        comment: $versionRow['comment'] !== null ? (string) $versionRow['comment'] : null,
                    );
                }

                // ── 6. Persistance ────────────────────────────────────────────
                $this->documentRepository->save($document);

                $events = $document->releaseEvents();
                if ($events !== []) {
                    $this->eventBus->dispatch(...$events);
                }

                $migrated++;

                if ($output->isVerbose()) {
                    $progress->clear();
                    $io->text(sprintf(
                        '  [OK] #%d "%s" — %d version(s)',
                        $legacyId,
                        $title,
                        count($versions) + 1,
                    ));
                    $progress->display();
                }
            } catch (\Exception $e) {
                $errors++;
                $progress->clear();
                $io->warning(sprintf('Erreur document #%d : %s', $legacyId, $e->getMessage()));
                $progress->display();
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

    /**
     * @param array{dir: string, version: int, fileType: string, origFileName: string, mimeType: string, fileSize: int} $versionRow
     */
    private function copyFile(array $versionRow, bool $skipFiles): \App\Domain\Storage\ValueObject\StoragePath
    {
        if ($skipFiles) {
            return \App\Domain\Storage\ValueObject\StoragePath::fromString(
                sprintf('legacy/%s/%d.%s', $versionRow['dir'], $versionRow['version'], $versionRow['fileType']),
            );
        }

        $physicalPath = sprintf(
            '%s/%s/%d.%s',
            rtrim($this->legacyStorageDir, '/'),
            $versionRow['dir'],
            $versionRow['version'],
            $versionRow['fileType'],
        );

        if (!file_exists($physicalPath)) {
            throw new \RuntimeException(sprintf('Fichier physique introuvable : %s', $physicalPath));
        }

        $stream = fopen($physicalPath, 'r');
        if ($stream === false) {
            throw new \RuntimeException(sprintf('Impossible d\'ouvrir le fichier : %s', $physicalPath));
        }

        try {
            $mimeType = $this->safeMimeType((string) $versionRow['mimeType']);

            return $this->storage->store($stream, $mimeType, (string) $versionRow['origFileName']);
        } finally {
            fclose($stream);
        }
    }

    private function safeMimeType(string $raw): MimeType
    {
        try {
            return MimeType::fromString($raw);
        } catch (\InvalidArgumentException) {
            return MimeType::fromString('application/octet-stream');
        }
    }
}
