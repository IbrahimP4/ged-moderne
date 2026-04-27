<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Charge .env + .env.test (APP_ENV=test) sans démarrer le Kernel
// Les tests unitaires n'ont pas besoin du Kernel — uniquement l'autoloader
if (file_exists(dirname(__DIR__) . '/.env.test')) {
    (new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__) . '/.env');
}

// Réduit le bruit des dépréciations vendor (Doctrine ORM proxy, API Platform)
// sans masquer les dépréciations de notre propre code
if (!isset($_ENV['SYMFONY_DEPRECATIONS_HELPER'])) {
    $_ENV['SYMFONY_DEPRECATIONS_HELPER'] = 'max[self]=0';
}
