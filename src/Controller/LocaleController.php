<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\SiteLanguage;
use App\Service\SiteUiTranslator;
use App\SiteUi\SiteLanguagePreference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    public function __construct(
        private readonly SiteUiTranslator $siteUi,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(path: '/locale', name: 'locale_switch', methods: ['POST'])]
    public function switch(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('locale_switch', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->siteUi->trans('flash.invalid_csrf'));

            return $this->redirectAfterLocaleSwitch($request);
        }

        $lang = SiteLanguage::tryFrom(strtoupper(trim((string) $request->request->get('language', ''))));
        if ($lang === null) {
            return $this->redirectAfterLocaleSwitch($request);
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setSiteLanguage($lang);
            $this->em->flush();
        } else {
            $request->getSession()->set(SiteLanguagePreference::SESSION_KEY, $lang->value);
        }

        $this->siteUi->clearRuntimeCache();

        return $this->redirectAfterLocaleSwitch($request);
    }

    /**
     * PRG with 303 + no-store so the browser performs a fresh GET with new locale / translations.
     */
    private function redirectAfterLocaleSwitch(Request $request): Response
    {
        $response = $this->redirect($this->safeTarget($request), Response::HTTP_SEE_OTHER);
        $response->headers->set('Cache-Control', 'no-store, private, must-revalidate');

        return $response;
    }

    private function safeTarget(Request $request): string
    {
        $target = (string) ($request->request->get('redirect') ?: '');
        if ($target === '') {
            $referer = $request->headers->get('Referer');
            if (\is_string($referer) && $referer !== '') {
                $parts = parse_url($referer);
                if (\is_array($parts) && isset($parts['path'])) {
                    $path = $parts['path'];
                    $query = isset($parts['query']) ? '?'.$parts['query'] : '';

                    return $path.$query;
                }
            }

            return '/';
        }

        if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return '/';
        }

        return $target;
    }
}
