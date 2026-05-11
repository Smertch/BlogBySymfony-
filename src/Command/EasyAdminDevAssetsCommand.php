<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'app:easyadmin-dev-assets',
    description: 'Symlink EasyAdmin vendor assets to logical paths under public/bundles/easyadmin-dev/ (dev URLs without hashed filenames).'
)]
final class EasyAdminDevAssetsCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manifestPath = $this->projectDir.'/vendor/easycorp/easyadmin-bundle/public/manifest.json';
        $vendorPublic = $this->projectDir.'/vendor/easycorp/easyadmin-bundle/public';
        $linkRoot = $this->projectDir.'/public/bundles/easyadmin-dev';

        if (!is_file($manifestPath)) {
            $output->writeln('<error>EasyAdmin manifest not found. Run composer install.</error>');

            return Command::FAILURE;
        }

        /** @var array<string, string> $manifest */
        $manifest = json_decode(file_get_contents($manifestPath), true, flags: \JSON_THROW_ON_ERROR);

        $fs = new Filesystem();
        if (!$fs->exists($linkRoot)) {
            $fs->mkdir($linkRoot);
        }

        foreach ($manifest as $logical => $physical) {
            $targetPath = $vendorPublic.'/'.$physical;
            if (!is_file($targetPath)) {
                $output->writeln(\sprintf('<comment>Skip %s (missing %s)</comment>', $logical, $physical));

                continue;
            }

            $linkPath = $linkRoot.'/'.$logical;
            $fs->mkdir(\dirname($linkPath));

            if (is_link($linkPath)) {
                $fs->remove($linkPath);
            } elseif ($fs->exists($linkPath)) {
                $fs->remove($linkPath);
            }

            $relativeTarget = Path::makeRelative($targetPath, \dirname($linkPath));
            $fs->symlink($relativeTarget, $linkPath);
        }

        $output->writeln('<info>EasyAdmin dev asset symlinks updated under public/bundles/easyadmin-dev/</info>');

        return Command::SUCCESS;
    }
}
