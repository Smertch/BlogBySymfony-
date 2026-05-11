<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class UserController extends AbstractController
{
    #[Route(path: '/api/user', name: 'api_user', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user, #[MapQueryParameter] ?string $debug = null): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        unset($debug);

        return new JsonResponse([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
        ]);
    }
}
