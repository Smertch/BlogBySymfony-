<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Local dev: EasyAdmin asset URLs use logical names (e.g. app.css) instead of
 * manifest-hashed filenames. Actual files are symlinked under public/bundles/easyadmin-dev/
 * by {@see EasyAdminDevAssetsCommand}.
 */
final class DevEasyAdminAssetPackage implements PackageInterface
{
    private readonly PackageInterface $package;

    public function __construct(RequestStack $requestStack)
    {
        $this->package = new PathPackage(
            '/bundles/easyadmin-dev',
            new EmptyVersionStrategy(),
            new RequestStackContext($requestStack)
        );
    }

    public function getUrl(string $path): string
    {
        return $this->package->getUrl($path);
    }

    public function getVersion(string $path): string
    {
        return $this->package->getVersion($path);
    }
}
