<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SwaggerController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'app.name')] private readonly string $appName,
        #[Autowire(param: 'app.url')] private readonly string $appUrl,
    ) {
    }

    #[Route(path: '/swagger', name: 'swagger', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('swagger/index.html.twig');
    }

    #[Route(path: '/swagger/spec', name: 'swagger.spec', methods: ['GET'])]
    public function spec(): JsonResponse
    {
        return new JsonResponse([
            'openapi' => '3.0.3',
            'info' => [
                'title' => $this->appName.' API',
                'description' => 'API documentation',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => $this->appUrl, 'description' => 'Current'],
            ],
            'paths' => [
                '/api/user' => [
                    'get' => [
                        'summary' => 'Current user',
                        'description' => 'Returns the authenticated user (Bearer token).',
                        'security' => [['ApiToken' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'Authenticated user',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer', 'example' => 1],
                                                'name' => ['type' => 'string'],
                                                'email' => ['type' => 'string', 'format' => 'email'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '401' => ['description' => 'Unauthenticated'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiToken' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'Authorization',
                        'description' => 'Bearer token (Symfony API token)',
                    ],
                ],
            ],
        ]);
    }
}
