<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RssFeedController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'app.name')] private readonly string $appName,
        #[Autowire(param: 'app.url')] private readonly string $appUrl,
    ) {
    }

    #[Route(path: '/feed', name: 'rss.feed', methods: ['GET'])]
    public function feed(PostRepository $posts): Response
    {
        $xml = $this->renderView('rss/feed.xml.twig', [
            'posts' => $posts->findLatestForFeed(50),
            'site_url' => rtrim($this->appUrl, '/'),
            'site_name' => $this->appName,
        ]);

        return new Response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
        ]);
    }

    #[Route(path: '/feed', name: 'rss.feed.options', methods: ['OPTIONS'])]
    public function options(): Response
    {
        return new Response('', 204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
        ]);
    }
}
