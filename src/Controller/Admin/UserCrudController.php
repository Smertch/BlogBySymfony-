<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly RequestStack $requestStack,
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
            ->setFormType(\Symfony\Component\Form\Extension\Core\Type\PasswordType::class)
            ->setFormTypeOption('mapped', false)
            ->setRequired($pageName === Crud::PAGE_NEW);
        yield ChoiceField::new('role')
            ->setChoices(array_combine(
                array_map(fn (UserRole $r) => $r->label(), UserRole::cases()),
                UserRole::cases(),
            ))
            ->renderAsBadges();
        yield DateTimeField::new('twoFactorConfirmedAt')->setLabel('2FA confirmed')->hideOnForm();
        yield DateTimeField::new('createdAt')->onlyOnIndex();
        yield DateTimeField::new('updatedAt')->onlyOnIndex();
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
