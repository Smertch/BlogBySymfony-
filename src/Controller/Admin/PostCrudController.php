<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Service\SiteUiTranslator;
use App\Util\AdminPagination;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminRoute(path: '/posts', name: 'posts')]
final class PostCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly PostRepository $postRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly SiteUiTranslator $siteUi,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Post::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Post')
            ->setEntityLabelInPlural('Posts')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title');
        yield AssociationField::new('user')->autocomplete();
        yield DateTimeField::new('createdAt')->onlyOnIndex();
        yield DateTimeField::new('updatedAt')->onlyOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        // DELETE / DETAIL go through our own custom routes / custom UI.
        return $actions->disable(Action::DETAIL, Action::DELETE);
    }

    /**
     * Custom index rendering aligned with the green-themed Posts management mockup.
     */
    public function index(AdminContext $context): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        $query = trim((string) ($request?->query->get('q') ?? ''));
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $perPage = 10;

        $result = $this->postRepository->searchPaginated(
            '' !== $query ? $query : null,
            $page,
            $perPage,
        );

        $total = $result['total'];
        $totalPages = (int) max(1, (int) ceil($total / $perPage));

        // Pre-build edit URLs per row. AdminUrlGenerator::generateUrl() resets its
        // internal parameter bag after each call, so consecutive calls are safe.
        $editUrls = [];
        foreach ($result['items'] as $p) {
            $editUrls[$p->getId()] = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Crud::PAGE_EDIT)
                ->setEntityId($p->getId())
                ->generateUrl();
        }

        $postsIndexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        $postsNewUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_NEW)
            ->generateUrl();

        $usersIndexUrl = $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->render('admin/posts/index.html.twig', [
            'sidebar_nav' => 'posts',
            'posts' => $result['items'],
            'total' => $total,
            'q' => $query,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'pagination_pages' => AdminPagination::compactPages($page, $totalPages),
            'edit_urls' => $editUrls,
            'urls' => [
                'posts_new' => $postsNewUrl,
                'posts_index' => $postsIndexUrl,
                'users_index' => $usersIndexUrl,
            ],
            'nav_urls' => [
                'users_index' => $usersIndexUrl,
                'posts_index' => $postsIndexUrl,
            ],
        ]);
    }

    /**
     * Delete a single post from the custom Posts index page.
     */
    #[Route('/admin/posts/{id}/delete', name: 'admin_posts_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deletePost(Post $post, Request $request): Response
    {
        $token = (string) $request->request->get('_token', '');
        $indexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        if (!$this->isCsrfTokenValid('delete-post-'.$post->getId(), $token)) {
            $this->addFlash('danger', $this->siteUi->trans('flash.invalid_csrf'));

            return new RedirectResponse($indexUrl);
        }

        $this->em->remove($post);
        $this->em->flush();
        $this->addFlash('success', $this->siteUi->trans('flash.post_deleted', [
            '%title%' => $post->getTitle(),
        ]));

        return new RedirectResponse($indexUrl);
    }

    /**
     * Bulk-delete posts selected via the table checkboxes.
     */
    #[Route('/admin/posts/bulk-delete', name: 'admin_posts_bulk_delete', methods: ['POST'])]
    public function bulkDeletePosts(Request $request): Response
    {
        $token = (string) $request->request->get('_token', '');
        $indexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        if (!$this->isCsrfTokenValid('bulk-delete-posts', $token)) {
            $this->addFlash('danger', $this->siteUi->trans('flash.invalid_csrf'));

            return new RedirectResponse($indexUrl);
        }

        /** @var array<int, int|string> $ids */
        $ids = (array) $request->request->all('ids');
        $deleted = $this->postRepository->deleteByIds($ids);

        if ($deleted > 0) {
            $this->addFlash('success', $this->siteUi->trans('flash.posts_bulk_deleted', [
                '%count%' => (string) $deleted,
            ]));
        } else {
            $this->addFlash('warning', $this->siteUi->trans('flash.posts_bulk_none'));
        }

        return new RedirectResponse($indexUrl);
    }
}
