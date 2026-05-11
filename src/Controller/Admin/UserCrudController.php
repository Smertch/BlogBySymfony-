<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Util\AdminPagination;
use App\Service\SiteUiTranslator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AdminRoute(path: '/users', name: 'users')]
final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly RequestStack $requestStack,
        private readonly UserRepository $userRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly SiteUiTranslator $siteUi,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name')->setRequired(true);
        yield EmailField::new('email')->setLabel('Email address')->setRequired(true);
        yield TextField::new('plainPassword', 'Password')
            ->onlyOnForms()
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('mapped', false)
            ->setRequired($pageName === Crud::PAGE_NEW);
        yield ChoiceField::new('role', 'Role')
            ->setChoices(array_combine(
                array_map(fn (UserRole $r) => $r->label(), UserRole::cases()),
                UserRole::cases(),
            ))
            ->onlyOnForms();
        yield DateTimeField::new('twoFactorConfirmedAt')->setLabel('2FA confirmed')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        // DETAIL/DELETE are disabled in EasyAdmin — delete is served by the custom
        // route below; EDIT stays enabled so the row-action link can open the EasyAdmin form.
        return $actions->disable(Action::DETAIL, Action::DELETE);
    }

    /**
     * Custom index rendering aligned with the green-themed Users management mockup.
     *
     * Replaces EasyAdmin's default list/table with our own template that uses
     * server-side search, 2FA filter and pagination.
     */
    public function index(AdminContext $context): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        $query = trim((string) ($request?->query->get('q') ?? ''));
        $twofa = (string) ($request?->query->get('twofa') ?? 'all');
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $perPage = 10;

        $result = $this->userRepository->searchPaginated(
            $query !== '' ? $query : null,
            $twofa,
            $page,
            $perPage,
        );

        $total = $result['total'];
        $totalPages = (int) max(1, (int) ceil($total / $perPage));

        // Build edit URLs per user. AdminUrlGenerator::generateUrl() resets its
        // internal parameter bag after each call, so consecutive calls are safe.
        $editUrls = [];
        foreach ($result['items'] as $u) {
            $editUrls[$u->getId()] = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Crud::PAGE_EDIT)
                ->setEntityId($u->getId())
                ->generateUrl();
        }

        $usersIndexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        $usersNewUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_NEW)
            ->generateUrl();

        $postsIndexUrl = $this->adminUrlGenerator
            ->setController(PostCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->render('admin/users/index.html.twig', [
            'sidebar_nav' => 'users',
            'users' => $result['items'],
            'total' => $total,
            'q' => $query,
            'twofa' => $twofa,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'pagination_pages' => AdminPagination::compactPages($page, $totalPages),
            'edit_urls' => $editUrls,
            'urls' => [
                'users_new' => $usersNewUrl,
                'users_index' => $usersIndexUrl,
                'posts_index' => $postsIndexUrl,
            ],
            'nav_urls' => [
                'users_index' => $usersIndexUrl,
                'posts_index' => $postsIndexUrl,
            ],
        ]);
    }

    /**
     * Delete handler called from the custom Users index page.
     * Validates a per-row CSRF token, removes the entity and redirects back to the list.
     */
    #[Route('/admin/users/{id}/delete', name: 'admin_users_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteUser(User $user, Request $request): Response
    {
        $token = (string) $request->request->get('_token', '');
        $indexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        if (!$this->isCsrfTokenValid('delete-user-'.$user->getId(), $token)) {
            $this->addFlash('danger', $this->siteUi->trans('flash.invalid_csrf'));

            return new RedirectResponse($indexUrl);
        }

        $this->em->remove($user);
        $this->em->flush();
        $this->addFlash('success', $this->siteUi->trans('flash.user_deleted', [
            '%name%' => $user->getName() ?: $user->getEmail(),
        ]));

        return new RedirectResponse($indexUrl);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPlainPassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPlainPassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPlainPassword(object $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $plain = null;
        foreach ($request->request->all() as $payload) {
            if (\is_array($payload) && isset($payload['plainPassword']) && \is_string($payload['plainPassword'])) {
                $plain = $payload['plainPassword'];
                break;
            }
        }

        if (\is_string($plain) && $plain !== '') {
            $entityInstance->setPassword($this->hasher->hashPassword($entityInstance, $plain));
        }
    }
}
