<?php

namespace App\DataFixtures;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\ValueObject\FileSize;
use App\Domain\Document\ValueObject\MimeType;
use App\Domain\Document\ValueObject\StoragePath;
use App\Domain\Folder\Entity\Folder;
use App\Domain\User\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // ===================================================================
        // 1. UTILISATEURS
        // ===================================================================
        // On ne peut plus se fier à un hash statique, car l'algo peut changer.
        // On utilise le service de hashage pour garantir que le mot de passe
        // correspond toujours à ce qui est attendu par le système de sécurité.

        $admin = User::create('admin', 'admin@ged.test', 'dummy-password', true);
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'admin1234')
        );
        $manager->persist($admin);
        $this->addReference('user-admin', $admin);

        $user1 = User::create('jdoe', 'john.doe@ged.test', 'dummy-password', false);
        $user1->setPassword(
            $this->passwordHasher->hashPassword($user1, 'user1234')
        );
        $manager->persist($user1);
        $this->addReference('user-jdoe', $user1);

        // ===================================================================
        // 2. DOSSIERS
        // ===================================================================

        $root = Folder::createRoot('Documents', $admin);
        $manager->persist($root);

        $folderCompta = Folder::create('Comptabilité', $root, $admin);
        $manager->persist($folderCompta);

        $folderRH = Folder::create('Ressources Humaines', $root, $user1);
        $manager->persist($folderRH);

        // ===================================================================
        // 3. DOCUMENTS (optionnel, pour avoir des données de test)
        // ===================================================================

        $doc1 = Document::upload(
            'Facture F2023-015', $folderCompta, $admin,
            MimeType::fromString('application/pdf'), FileSize::fromKilobytes(128),
            'F2023-015.pdf', StoragePath::fromString('fixtures/facture.pdf'),
            'Facture client DUPONT'
        );
        $manager->persist($doc1);

        $doc2 = Document::upload(
            'Contrat John Doe', $folderRH, $user1,
            MimeType::fromString('application/pdf'), FileSize::fromKilobytes(256),
            'contrat_jdoe.pdf', StoragePath::fromString('fixtures/contrat.pdf'),
            'Contrat de travail'
        );
        $manager->persist($doc2);

        $manager->flush();
    }
}
