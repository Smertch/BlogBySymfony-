<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RegistrationWelcomeMail;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class RegistrationWelcomeMailHandler
{
    public function __construct(
        private UserRepository $users,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Autowire(param: 'app.name')]
        private string $appName,
        #[Autowire(env: 'MAIL_FROM_ADDRESS')]
        private string $fromAddress,
        #[Autowire(env: 'MAIL_FROM_NAME')]
        private string $fromName,
    ) {
    }

    public function __invoke(RegistrationWelcomeMail $message): void
    {
        $user = $this->users->find($message->userId);
        if (null === $user) {
            return;
        }

        $subject = $this->translator->trans('registration_welcome_subject', [
            'app' => $this->appName,
        ], 'auth');

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject($subject)
            ->htmlTemplate('emails/registration_welcome.html.twig')
            ->context([
                'user' => $user,
                'app_name' => $this->appName,
            ]);

        $this->mailer->send($email);
    }
}
