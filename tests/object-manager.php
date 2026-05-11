<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

/** @var Doctrine\ORM\EntityManagerInterface $em */
$em = $kernel->getContainer()->get('doctrine')->getManager();

return $em;
