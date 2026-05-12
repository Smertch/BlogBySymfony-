<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\SiteLanguage;
use App\Form\ForgotPasswordFormType;
use App\Form\RegistrationFormType;
use App\Form\ResetPasswordFormType;
use App\Message\RegistrationWelcomeMail;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\SiteUiTranslator;
use App\SiteUi\SiteLanguagePreference;
use LogicException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly SiteUiTranslator $siteUi,
    ) {
    }

    #[Route(path: '/login', name: 'login', methods: ['GET'])]
    public function loginShow(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Real authentication is performed by Symfony's form_login firewall (see config/packages/security.yaml).
     * This action exists only so the route name `login.store` can be referenced in templates.
     */
    #[Route(path: '/login', name: 'login.store', methods: ['POST'])]
    public function loginStore(): Response
    {
        throw new LogicException('This method is intercepted by the form_login authenticator in security.yaml.');
    }

    #[Route(path: '/logout', name: 'logout', methods: ['POST', 'GET'])]
    public function logout(): never
    {
        throw new LogicException('Intercepted by the logout listener in security.yaml.');
    }

    #[Route(path: '/register', name: 'register', methods: ['GET'])]
    public function registerShow(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $form = $this->createForm(RegistrationFormType::class);

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/register', name: 'register.store', methods: ['POST'])]
    public function registerStore(
        Request $request,
        UserPasswordHasherInterface $hasher,
        UserRepository $users,
        MessageBusInterface $bus,
    ): Response {
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('auth/register.html.twig', [
                'form' => $form,
            ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $data = $form->getData();
        if (null !== $users->findByEmail((string) $data['email'])) {
            $this->addFlash('error', $this->siteUi->trans('flash.email_taken'));

            return $this->redirectToRoute('register');
        }

        $user = new User();
        $user->setName((string) $data['name']);
        $user->setEmail((string) $data['email']);
        $user->setPassword($hasher->hashPassword($user, (string) $form->get('password')->getData()));

        $guestLangRaw = $request->getSession()->get(SiteLanguagePreference::SESSION_KEY);
        if (\is_string($guestLangRaw)) {
            $guestLang = SiteLanguage::tryFrom(strtoupper(trim($guestLangRaw)));
            if (null !== $guestLang) {
                $user->setSiteLanguage($guestLang);
            }
        }

        $users->save($user);

        $userId = $user->getId();
        if (null !== $userId) {
            $bus->dispatch(new RegistrationWelcomeMail($userId));
        }

        $this->addFlash('success', $this->siteUi->trans('flash.account_created'));

        return $this->redirectToRoute('login');
    }

    #[Route(path: '/forgot-password', name: 'password.request', methods: ['GET'])]
    public function forgotPasswordShow(): Response
    {
        return $this->render('auth/forgot_password.html.twig', [
            'form' => $this->createForm(ForgotPasswordFormType::class),
        ]);
    }

    #[Route(path: '/forgot-password', name: 'password.email', methods: ['POST'])]
    public function forgotPasswordSend(
        Request $request,
        UserRepository $users,
        PasswordResetTokenRepository $tokens,
        MailerInterface $mailer,
        TranslatorInterface $translator,
        #[Autowire(env: 'MAIL_FROM_ADDRESS')] string $fromAddress,
        #[Autowire(env: 'MAIL_FROM_NAME')] string $fromName,
    ): Response {
        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('auth/forgot_password.html.twig', [
                'form' => $form,
            ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $email = (string) $form->get('email')->getData();
        $user = $users->findByEmail($email);

        if (null !== $user) {
            $rawToken = bin2hex(random_bytes(32));
            $tokens->upsert($email, password_hash($rawToken, \PASSWORD_BCRYPT));

            $resetUrl = $this->generateUrl(
                'password.reset',
                ['token' => $rawToken, 'email' => $email],
                \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $mail = (new TemplatedEmail())
                ->from(new Address($fromAddress, $fromName))
                ->to(new Address($email))
                ->subject($translator->trans('password_reset_subject', [], 'auth'))
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context([
                    'reset_url' => $resetUrl,
                ]);

            $mailer->send($mail);
        }

        $this->addFlash('status', $this->siteUi->trans('flash.password_reset_sent'));

        return $this->redirectToRoute('password.request');
    }

    #[Route(path: '/reset-password/{token}', name: 'password.reset', methods: ['GET'])]
    public function resetPasswordShow(string $token, Request $request): Response
    {
        $form = $this->createForm(ResetPasswordFormType::class, [
            'token' => $token,
            'email' => (string) $request->query->get('email', ''),
        ]);

        return $this->render('auth/reset_password.html.twig', [
            'form' => $form,
            'request_email' => (string) $request->query->get('email', ''),
            'request_token' => $token,
        ]);
    }

    #[Route(path: '/reset-password', name: 'password.update', methods: ['POST'])]
    public function resetPasswordUpdate(
        Request $request,
        UserRepository $users,
        PasswordResetTokenRepository $tokens,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $submitted = (array) $request->request->all('reset_password_form');

            return $this->render('auth/reset_password.html.twig', [
                'form' => $form,
                'request_email' => (string) ($submitted['email'] ?? ''),
                'request_token' => (string) ($submitted['token'] ?? ''),
            ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $data = $form->getData();
        $email = (string) $data['email'];
        $rawToken = (string) $data['token'];

        $row = $tokens->findByEmail($email);
        if (null === $row || !password_verify($rawToken, $row->getToken())) {
            $this->addFlash('error', $this->siteUi->trans('flash.password_reset_invalid_token'));

            return $this->redirectToRoute('login');
        }

        $user = $users->findByEmail($email);
        if (null === $user) {
            $this->addFlash('error', $this->siteUi->trans('flash.user_not_found'));

            return $this->redirectToRoute('login');
        }

        $user->setPassword($hasher->hashPassword($user, (string) $form->get('password')->getData()));
        $users->save($user);
        $tokens->deleteByEmail($email);

        $this->addFlash('status', $this->siteUi->trans('flash.password_reset_done'));

        return $this->redirectToRoute('login');
    }

    #[Route(path: '/2fa', name: '2fa_login')]
    #[IsGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS')]
    public function twoFactorLogin(): Response
    {
        return $this->render('auth/2fa.html.twig');
    }

    #[Route(path: '/2fa_check', name: '2fa_login_check', methods: ['POST'])]
    public function twoFactorCheck(): never
    {
        throw new LogicException('Intercepted by the two_factor firewall listener.');
    }
}
