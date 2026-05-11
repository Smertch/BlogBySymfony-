<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PostRepository $posts,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public function index(): Response
    {
        $weekAgo = new \DateTimeImmutable('-7 days');

        $urls = [
            'users_index' => $this->adminUrlGenerator
                ->setController(UserCrudController::class)
                ->setAction(Crud::PAGE_INDEX)
                ->generateUrl(),
            'users_new' => $this->adminUrlGenerator
                ->setController(UserCrudController::class)
                ->setAction(Crud::PAGE_NEW)
                ->generateUrl(),
            'posts_index' => $this->adminUrlGenerator
                ->setController(PostCrudController::class)
                ->setAction(Crud::PAGE_INDEX)
                ->generateUrl(),
            'posts_new' => $this->adminUrlGenerator
                ->setController(PostCrudController::class)
                ->setAction(Crud::PAGE_NEW)
                ->generateUrl(),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users_total' => $this->users->countAll(),
                'users_with_2fa' => $this->users->countWithTwoFactor(),
                'posts_total' => $this->posts->countAll(),
                'posts_week' => $this->posts->countCreatedSince($weekAgo),
            ],
            'recent_users' => $this->users->findLatest(5),
            'recent_posts' => $this->posts->findLatestForFeed(5),
            'urls' => $urls,
            'nav_urls' => $urls,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Blog Admin')
            ->setFaviconPath('favicon.ico');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToUrl('Home', 'fa fa-arrow-left', '/');
        yield MenuItem::section('Content');
        yield MenuItem::linkToCrud('Users', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Posts', 'fa fa-newspaper', Post::class);
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return UserMenu::new()
            ->displayUserName()
            ->displayUserAvatar()
            ->setName($user->getUserIdentifier())
            ->setMenuItems([]);
    }
}
