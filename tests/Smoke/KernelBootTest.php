<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Sanity check that the Symfony container compiles and the kernel can boot
 * in the `test` environment. Catches misconfigured services, broken DI, or
 * invalid YAML before any feature test runs.
 */
final class KernelBootTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        $kernel = self::bootKernel(['environment' => 'test']);

        self::assertSame('test', $kernel->getEnvironment());
        self::assertTrue($kernel->getContainer()->has('doctrine'));
        self::assertTrue($kernel->getContainer()->has('twig'));
    }

    public function testRoutesAreLoaded(): void
    {
        self::bootKernel(['environment' => 'test']);

        $router = self::getContainer()->get('router');
        self::assertNotNull($router);

        $routes = $router->getRouteCollection();
        self::assertNotNull($routes->get('home'), 'Route "home" must be defined.');
        self::assertNotNull($routes->get('login'), 'Route "login" must be defined.');
        self::assertNotNull($routes->get('register'), 'Route "register" must be defined.');
        self::assertNotNull($routes->get('rss.feed'), 'Route "rss.feed" must be defined.');
        self::assertNotNull($routes->get('swagger'), 'Route "swagger" must be defined.');
    }
}
