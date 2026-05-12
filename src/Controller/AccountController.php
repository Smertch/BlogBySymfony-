<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountPasswordFormType;
use App\Form\AccountProfileFormType;
use App\Repository\PostRepository;
use App\Service\SiteUiTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountController extends AbstractController
{
    public function __construct(
        private readonly SiteUiTranslator $siteUi,
    ) {
    }

    #[Route(path: '/account', name: 'account', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(PostRepository $postRepository): Response
    {
        $user = $this->requireUser();

        $profileForm = $this->createForm(AccountProfileFormType::class, ['name' => $user->getName()]);
        $passwordForm = $this->createForm(AccountPasswordFormType::class);

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'posts' => $postRepository->findByUser($user),
            'profile_form' => $profileForm,
            'password_form' => $passwordForm,
        ]);
    }

    #[Route(path: '/account/profile', name: 'account_profile_update', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateProfile(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requireUser();

        $form = $this->createForm(AccountProfileFormType::class, ['name' => $user->getName()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->siteUi->trans('account.flash.profile_invalid'));

            return $this->redirectToRoute('account');
        }

        $user->setName(trim((string) $form->get('name')->getData()));
        $em->flush();

        $this->addFlash('success', $this->siteUi->trans('account.flash.profile_saved'));

        return $this->redirectToRoute('account');
    }

    #[Route(path: '/account/password', name: 'account_password_update', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updatePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $this->requireUser();

        $form = $this->createForm(AccountPasswordFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->siteUi->trans('account.flash.password_invalid'));

            return $this->redirectToRoute('account');
        }

        $current = (string) $form->get('currentPassword')->getData();
        if (!$hasher->isPasswordValid($user, $current)) {
            $this->addFlash('error', $this->siteUi->trans('account.flash.wrong_password'));

            return $this->redirectToRoute('account');
        }

        /** @var string $newPlain */
        $newPlain = $form->get('newPassword')->getData();
        $user->setPassword($hasher->hashPassword($user, $newPlain));
        $em->flush();

        $this->addFlash('success', $this->siteUi->trans('account.flash.password_changed'));

        return $this->redirectToRoute('account');
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
