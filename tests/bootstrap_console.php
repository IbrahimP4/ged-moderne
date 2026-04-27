<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/.env')) {
    (new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__) . '/.env');
}

$kernel = new Kernel('test', true);

return new Application($kernel);
