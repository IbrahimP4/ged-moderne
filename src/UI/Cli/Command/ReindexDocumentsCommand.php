<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Document\Command\IndexDocumentContentCommand;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Entity\DocumentVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Commande de ré-indexation du contenu textuel de tous les documents.
 *
 * Utile pour :
 *   - Indexer les documents déjà présents avant l'ajout de la recherche full-text
 *   - Re-indexer après une migration ou une mise à jour du TextExtractorService
 *   - Corriger des indexations corrompues
 *
 * Usage :
 *   php bin/console ged:reindex-documents
 *   php bin/console ged:reindex-documents --force          # Re-indexe même les docs déjà indexés
 *   php bin/console ged:reindex-documents --batch-size=20  # Taille des lots Doctrine
 *   php bin/console ged:reindex-documents --dry-run        # Affiche ce qui serait fait sans agir
 */
#[AsCommand(
    name: 'ged:reindex-documents',
    description: 'Ré-indexe le contenu textuel de tous les documents pour la recherche full-text.',
)]
final class ReindexDocumentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface    $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Re-indexe même les documents dont le contenu est déjà indexé',
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre de documents traités par lot Doctrine (évite les OOM)',
                '50',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Affiche les documents qui seraient indexés sans déclencher l\'indexation',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $force     = (bool) $input->getOption('force');
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $dryRun    = (bool) $input->getOption('dry-run');

        $io->title('GED — Ré-indexation du contenu des documents');

        if ($dryRun) {
            $io->warning('Mode DRY-RUN : aucune indexation ne sera effectuée.');
        }

        // ── Compter les documents à traiter ───────────────────────────────────
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->where('d.deletedAt IS NULL');

        if (!$force) {
            // Seulement les docs dont aucune version n'a de contenu indexé
            $qb->leftJoin('d.versions', 'v')
               ->andWhere('v.contentText IS NULL');
        }

        $total = (int) $qb->getQuery()->getSingleScalarResult();

        if ($total === 0) {
            $io->success('Tous les documents sont déjà indexés. Utilisez --force pour ré-indexer.');
            return Command::SUCCESS;
        }

        $io->info(sprintf(
            '%d document(s) à indexer%s.',
            $total,
            $force ? ' (y compris ceux déjà indexés)' : ' (non encore indexés)',
        ));

        // ── Traitement par lots ───────────────────────────────────────────────
        $progressBar = $io->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $progressBar->start();

        $offset    = 0;
        $processed = 0;
        $skipped   = 0;
        $errors    = 0;

        while (true) {
            $qb = $this->entityManager->createQueryBuilder()
                ->select('d')
                ->from(Document::class, 'd')
                ->where('d.deletedAt IS NULL')
                ->orderBy('d.createdAt', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize);

            if (!$force) {
                $qb->leftJoin('d.versions', 'v')
                   ->andWhere('v.contentText IS NULL');
            }

            /** @var list<Document> $documents */
            $documents = $qb->getQuery()->getResult();

            if (empty($documents)) {
                break;
            }

            foreach ($documents as $document) {
                $latestVersion = $this->getLatestVersion($document);

                if ($latestVersion === null) {
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                $mimeType    = $latestVersion->getMimeType()->getValue();
                $storagePath = $latestVersion->getStoragePath()->getValue();

                $progressBar->setMessage(sprintf('« %s »', mb_substr($document->getTitle(), 0, 40)));

                if ($dryRun) {
                    $io->writeln(sprintf(
                        "\n  [DRY-RUN] id=%s | %s | type=%s",
                        $document->getId()->getValue(),
                        $document->getTitle(),
                        $mimeType,
                    ));
                    $processed++;
                    $progressBar->advance();
                    continue;
                }

                try {
                    $this->messageBus->dispatch(new IndexDocumentContentCommand(
                        documentId:  $document->getId(),
                        storagePath: $storagePath,
                        mimeType:    $mimeType,
                    ));
                    $processed++;
                } catch (\Throwable $e) {
                    $errors++;
                    $io->error(sprintf(
                        'Erreur sur « %s » : %s',
                        $document->getTitle(),
                        $e->getMessage(),
                    ));
                }

                $progressBar->advance();
            }

            // Libérer la mémoire Doctrine entre chaque lot
            $this->entityManager->clear();

            $offset += $batchSize;

            // Sécurité : si on a traité moins que le batchSize, c'est le dernier lot
            if (count($documents) < $batchSize) {
                break;
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        // ── Résumé ────────────────────────────────────────────────────────────
        $io->definitionList(
            ['Documents traités'  => $processed],
            ['Documents ignorés'  => $skipped . ' (sans version)'],
            ['Erreurs'            => $errors],
            ['Mode'               => $dryRun ? 'DRY-RUN (aucune indexation)' : 'Indexation déclenchée'],
        );

        if ($errors === 0) {
            $io->success($dryRun
                ? sprintf('%d document(s) seraient indexés.', $processed)
                : sprintf('%d document(s) soumis à l\'indexation avec succès.', $processed),
            );
        } else {
            $io->warning(sprintf('%d erreur(s) lors de l\'indexation.', $errors));
        }

        return $errors === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function getLatestVersion(Document $document): ?DocumentVersion
    {
        $versions = $document->getVersions()->toArray();
        if (empty($versions)) {
            return null;
        }

        usort($versions, fn ($a, $b) => $b->getVersionNumber()->getValue() <=> $a->getVersionNumber()->getValue());

        return $versions[0];
    }
}
