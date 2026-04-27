<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\User\Entity\User;
use App\Infrastructure\Security\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadata   = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    protected function loginAs(User $user): void
    {
        $securityUser = new SecurityUser($user);
        $this->client->loginUser($securityUser);
    }

    protected function jsonRequest(string $method, string $uri, array $data = [], array $files = []): array
    {
        $this->client->request(
            method: $method,
            uri: $uri,
            parameters: [],
            files: $files,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: empty($files) && !empty($data) ? json_encode($data, JSON_THROW_ON_ERROR) : null,
        );

        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
