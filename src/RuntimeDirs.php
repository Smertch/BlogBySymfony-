<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;

/**
 * Ensures var/cache/<env>, var/log and the effective kernel build directory exist and are writable.
 */
final class RuntimeDirs
{
    /**
     * Mirrors Symfony Kernel logic: {@see HttpKernel::initializeContainer()} uses
     * {@see HttpKernel::$warmupDir} ?: {@see HttpKernel::getBuildDir()} before dumping the container.
     */
    public static function prepareKernelCache(HttpKernel $kernel): void
    {
        $projectDir = $kernel->getProjectDir();
        $env = $kernel->getEnvironment();

        self::ensure($projectDir, $env);

        $warmupDir = self::readWarmupDir($kernel);
        $buildDir = $warmupDir ?: $kernel->getBuildDir();

        $fs = new Filesystem();
        $fs->mkdir($buildDir, 0777);

        try {
            $fs->chmod($buildDir, 0777);
        } catch (\Throwable) {
        }

        self::assertWritable($buildDir);
    }

    private static function readWarmupDir(HttpKernel $kernel): ?string
    {
        try {
            $property = new \ReflectionProperty(HttpKernel::class, 'warmupDir');
            $property->setAccessible(true);

            /** @var string|null $value */
            $value = $property->getValue($kernel);

            return $value ?: null;
        } catch (\ReflectionException) {
            return null;
        }
    }

    private static function assertWritable(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException(sprintf('Expected cache/build directory "%s" is missing after mkdir.', $dir));
        }

        $probe = $dir.'/._symfony_write_probe_'.bin2hex(random_bytes(4));
        if (false === @file_put_contents($probe, 'ok')) {
            throw new \RuntimeException(sprintf(
                'Cannot write into "%s". On Docker bind mounts run as root in entrypoint: chown -R www-data:www-data var && chmod -R a+rwX var',
                $dir
            ));
        }

        @unlink($probe);
    }

    /**
     * @internal Used by tests or tooling that only need the base tree
     */
    public static function ensure(string $projectDir, string $env): void
    {
        $fs = new Filesystem();

        foreach ([
            $projectDir.'/var',
            $projectDir.'/var/cache',
            $projectDir.'/var/cache/'.$env,
            $projectDir.'/var/log',
        ] as $dir) {
            if (is_dir($dir)) {
                continue;
            }

            if (file_exists($dir)) {
                throw new \RuntimeException(sprintf(
                    'Cannot create directory "%s": a non-directory file already exists at this path.',
                    $dir
                ));
            }

            $fs->mkdir($dir, 0777);
        }
    }
}
