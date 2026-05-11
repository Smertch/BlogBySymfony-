<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel((string) ($_SERVER['APP_ENV'] ?? 'test'), (bool) ($_SERVER['APP_DEBUG'] ?? false));

return new Application($kernel);
