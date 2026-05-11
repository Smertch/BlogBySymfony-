<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteTranslation;
use App\Enum\SiteLanguage;
use App\Repository\SiteTranslationRepository;
use App\Service\SiteUiTranslator;
use App\Util\AdminPagination;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminRoute(path: '/translations', name: 'translations')]
final class SiteTranslationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SiteTranslationRepository $siteTranslations,
        private readonly RequestStack $requestStack,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly SiteUiTranslator $siteUi,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SiteTranslation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Translation')
            ->setEntityLabelInPlural('Translations')
            ->setPageTitle('index', 'Translations')
            ->setDefaultSort(['alias' => 'ASC'])
            ->setSearchFields(['alias', 'translate']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::DELETE);
    }

    public function index(AdminContext $context): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        $query = trim((string) ($request?->query->get('q') ?? ''));
        $langFilter = (string) ($request?->query->get('lang') ?? 'all');
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $perPage = 20;

        $result = $this->siteTranslations->searchPaginated(
            '' !== $query ? $query : null,
            $langFilter,
            $page,
            $perPage,
        );

        $total = $result['total'];
        $totalPages = (int) max(1, (int) ceil($total / $perPage));

        $editUrls = [];
        foreach ($result['items'] as $row) {
            $editUrls[$row->getId()] = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Crud::PAGE_EDIT)
                ->setEntityId($row->getId())
                ->generateUrl();
        }

        $translationsIndexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        $translationsNewUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_NEW)
            ->generateUrl();

        $usersIndexUrl = $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        $postsIndexUrl = $this->adminUrlGenerator
            ->setController(PostCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        $first = 0 === $total ? 0 : (($page - 1) * $perPage) + 1;
        $last = min($page * $perPage, $total);

        $metaTitle = $this->siteUi->trans('admin.translations.title')
            .' · '
            .$this->siteUi->trans('admin.nav.blog_admin');

        return $this->render('admin/translations/index.html.twig', [
            'sidebar_nav' => 'translations',
            'meta_title' => $metaTitle,
            'translations' => $result['items'],
            'total' => $total,
            'q' => $query,
            'lang' => $langFilter,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'range_first' => $first,
            'range_last' => $last,
            'edit_urls' => $editUrls,
            'pagination_pages' => AdminPagination::compactPages($page, $totalPages),
            'urls' => [
                'translations_new' => $translationsNewUrl,
                'translations_index' => $translationsIndexUrl,
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
     * CSV export of all rows (alias, language, text).
     */
    #[Route('/admin/translations/export.csv', name: 'admin_translations_export', methods: ['GET'])]
    public function exportCsv(): Response
    {
        $rows = $this->siteTranslations->findBy([], ['alias' => 'ASC', 'languageType' => 'ASC']);

        $fh = fopen('php://temp', 'r+');
        if (false === $fh) {
            throw new RuntimeException('Cannot open temp stream.');
        }

        fputcsv($fh, ['id', 'alias', 'language', 'translate']);
        foreach ($rows as $t) {
            fputcsv($fh, [
                $t->getId(),
                $t->getAlias(),
                $t->getLanguageType()->value,
                $t->getTranslate(),
            ]);
        }

        rewind($fh);
        $content = stream_get_contents($fh) ?: '';
        fclose($fh);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="translations.csv"',
        ]);
    }

    #[Route('/admin/translations/{id}/remove', name: 'admin_translation_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteTranslation(SiteTranslation $translation, Request $request): Response
    {
        $token = (string) $request->request->get('_token', '');
        $indexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        if (!$this->isCsrfTokenValid('delete-translation-'.$translation->getId(), $token)) {
            $this->addFlash('danger', $this->siteUi->trans('flash.invalid_csrf'));

            return new RedirectResponse($indexUrl);
        }

        $alias = $translation->getAlias();
        $lang = $translation->getLanguageType()->value;

        $this->em->remove($translation);
        $this->em->flush();

        $this->addFlash('success', $this->siteUi->trans('flash.translation_deleted', [
            '%alias%' => $alias,
            '%lang%' => $lang,
        ]));

        return new RedirectResponse($indexUrl);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('alias')
            ->setRequired(true)
            ->setHelp('Stable key (together with language). Shown on the site if no row matches.');
        yield ChoiceField::new('languageType', 'Language')
            ->setChoices([
                'EN' => SiteLanguage::EN,
                'ES' => SiteLanguage::ES,
                'DE' => SiteLanguage::DE,
                'UA' => SiteLanguage::UA,
            ])
            ->setRequired(true);
        yield TextareaField::new('translate', 'Translation')
            ->setRequired(true);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('languageType', 'Language')->setChoices([
            'EN' => SiteLanguage::EN,
            'ES' => SiteLanguage::ES,
            'DE' => SiteLanguage::DE,
            'UA' => SiteLanguage::UA,
        ]));
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->normalizeTranslation($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->normalizeTranslation($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function normalizeTranslation(object $entityInstance): void
    {
        if (!$entityInstance instanceof SiteTranslation) {
            return;
        }

        $entityInstance->setAlias(trim($entityInstance->getAlias()));
        $entityInstance->setTranslate(trim($entityInstance->getTranslate()));
    }
}
