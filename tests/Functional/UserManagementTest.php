<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\UserRole;
use LogicException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional coverage for the user lifecycle:
 *
 *  - public registration (POST /register) creates a new ROLE_USER account,
 *  - the admin panel can create / edit users via EasyAdmin forms,
 *  - the custom POST /admin/users/{id}/delete route removes a user with CSRF,
 *  - non-admins cannot access or mutate any of the above admin endpoints.
 */
final class UserManagementTest extends FunctionalTestCase
{
    // -----------------------------------------------------------------
    // Public registration
    // -----------------------------------------------------------------

    public function testGuestCanRegisterAndAccountIsPersisted(): void
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $this->grabFirstForm($crawler, '#registerForm form');
        $form['registration_form[name]'] = 'New User';
        $form['registration_form[email]'] = 'newcomer@example.com';
        $form['registration_form[password][first]'] = 'super-secret-123';
        $form['registration_form[password][second]'] = 'super-secret-123';

        $this->client->submit($form);

        self::assertResponseRedirects('/login');
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'newcomer@example.com']);
        self::assertNotNull($user);
        self::assertSame('New User', $user->getName());
        self::assertSame(UserRole::User, $user->getRole());
        // Stored password must be hashed, not plain.
        self::assertNotSame('super-secret-123', $user->getPassword());
        self::assertTrue($this->hasher->isPasswordValid($user, 'super-secret-123'));
    }

    public function testRegistrationRejectsDuplicateEmail(): void
    {
        $this->createUser('taken@example.com');

        $crawler = $this->client->request('GET', '/register');
        $form = $this->grabFirstForm($crawler, '#registerForm form');
        $form['registration_form[name]'] = 'Duplicate';
        $form['registration_form[email]'] = 'taken@example.com';
        $form['registration_form[password][first]'] = 'super-secret-123';
        $form['registration_form[password][second]'] = 'super-secret-123';

        $this->client->submit($form);

        self::assertResponseRedirects('/register');
        // Only the originally created user must exist.
        self::assertSame(1, $this->em->getRepository(User::class)->count([]));
    }

    public function testRegistrationRejectsMismatchedPasswords(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $this->grabFirstForm($crawler, '#registerForm form');
        $form['registration_form[name]'] = 'Mismatch';
        $form['registration_form[email]'] = 'mismatch@example.com';
        $form['registration_form[password][first]'] = 'super-secret-123';
        $form['registration_form[password][second]'] = 'other-secret-456';

        $this->client->submit($form);

        // 422 means the form re-renders with validation errors; no DB row.
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));
    }

    public function testRegistrationRejectsShortPassword(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $this->grabFirstForm($crawler, '#registerForm form');
        $form['registration_form[name]'] = 'Shorty';
        $form['registration_form[email]'] = 'shorty@example.com';
        $form['registration_form[password][first]'] = 'abc';
        $form['registration_form[password][second]'] = 'abc';

        $this->client->submit($form);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));
    }

    public function testRegistrationRejectsInvalidEmail(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $this->grabFirstForm($crawler, '#registerForm form');
        $form['registration_form[name]'] = 'Bad Email';
        $form['registration_form[email]'] = 'not-an-email';
        $form['registration_form[password][first]'] = 'super-secret-123';
        $form['registration_form[password][second]'] = 'super-secret-123';

        $this->client->submit($form);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, $this->em->getRepository(User::class)->count([]));
    }

    // -----------------------------------------------------------------
    // Admin: create user via EasyAdmin form
    // -----------------------------------------------------------------

    public function testAdminCanCreateUserViaAdminPanel(): void
    {
        $admin = $this->createUser('admin@example.com', admin: true);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();

        $form = $this->grabFirstForm($crawler);
        $form['User[name]'] = 'Created By Admin';
        $form['User[email]'] = 'created@example.com';
        $form['User[plainPassword]'] = 'top-secret';
        $form['User[role]'] = $this->roleChoiceValue(UserRole::User);

        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'created@example.com']);
        self::assertNotNull($user);
        self::assertSame('Created By Admin', $user->getName());
        self::assertSame(UserRole::User, $user->getRole());
        self::assertTrue($this->hasher->isPasswordValid($user, 'top-secret'));
    }

    public function testAdminCanCreateAnotherAdmin(): void
    {
        $admin = $this->createUser('admin@example.com', admin: true);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users/new');
        $form = $this->grabFirstForm($crawler);
        $form['User[name]'] = 'Second Admin';
        $form['User[email]'] = 'admin2@example.com';
        $form['User[plainPassword]'] = 'top-secret';
        $form['User[role]'] = $this->roleChoiceValue(UserRole::Admin);

        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->em->clear();
        $created = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin2@example.com']);
        self::assertNotNull($created);
        self::assertSame(UserRole::Admin, $created->getRole());
        self::assertTrue($created->isAdmin());
    }

    // -----------------------------------------------------------------
    // Admin: edit user via EasyAdmin form
    // -----------------------------------------------------------------

    public function testAdminCanEditUserName(): void
    {
        $admin = $this->createUser('admin@example.com', admin: true);
        $victim = $this->createUser('victim@example.com');
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users/'.$victim->getId().'/edit');
        self::assertResponseIsSuccessful();

        $form = $this->grabFirstForm($crawler);
        $form['User[name]'] = 'Renamed';
        $form['User[email]'] = 'victim@example.com';
        // Leave plainPassword empty so the existing hash is preserved.

        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $victim->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Renamed', $reloaded->getName());
    }

    public function testAdminCanChangeUserPasswordViaEditForm(): void
    {
        $admin = $this->createUser('admin@example.com', admin: true);
        $victim = $this->createUser('victim@example.com', password: 'old-password');
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users/'.$victim->getId().'/edit');
        $form = $this->grabFirstForm($crawler);
        $form['User[name]'] = $victim->getName();
        $form['User[email]'] = $victim->getEmail();
        $form['User[plainPassword]'] = 'fresh-password';

        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $victim->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($this->hasher->isPasswordValid($reloaded, 'fresh-password'));
        self::assertFalse($this->hasher->isPasswordValid($reloaded, 'old-password'));
    }

    public function testAdminLeavesPasswordUntouchedWhenLeftBlank(): void
    {
        $admin = $this->createUser('admin@example.com', admin: true);
        $victim = $this->createUser('victim@example.com', password: 'keep-this');
        $originalHash = $victim->getPassword();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users/'.$victim->getId().'/edit');
        $form = $this->grabFirstForm($crawler);
        $form['User[name]'] = 'Different Name';
        $form['User[email]'] = $victim->getEmail();
        $form['User[plainPassword]'] = '';

        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $victim->getId());
        self::assertNotNull($reloaded);
        self::assertSame($originalHash, $reloaded->getPassword());
        self::assertTrue($this->hasher->isPasswordValid($reloaded, 'keep-this'));
    }

    public function testAdminCanPromoteUserToAdmin(): void
    {
        $admin = $this->createUser('admin@example.com', admin: true);
        $regular = $this->createUser('regular@example.com');
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users/'.$regular->getId().'/edit');
        $form = $this->grabFirstForm($crawler);
        $form['User[name]'] = $regular->getName();
        $form['User[email]'] = $regular->getEmail();
        $form['User[role]'] = $this->roleChoiceValue(UserRole::Admin);

        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $regular->getId());
        self::assertNotNull($reloaded);
        self::assertSame(UserRole::Admin, $reloaded->getRole());
    }

    // -----------------------------------------------------------------
    // Admin: delete user via the custom POST route
    // -----------------------------------------------------------------

    public function testAdminCanDeleteUser(): void
    {
        $admin = $this->createUser('admin@example.com', admin: true);
        $victim = $this->createUser('victim@example.com');
        $victimId = $victim->getId();
        $this->client->loginUser($admin);

        $token = $this->extractUserDeleteToken($victimId);
        $this->client->request('POST', '/admin/users/'.$victimId.'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->find(User::class, $victimId));
        // The admin who issued the delete must still exist.
        self::assertNotNull($this->em->find(User::class, $admin->getId()));
    }

    public function testUserDeletionRejectsInvalidCsrfToken(): void
    {
        $admin = $this->createUser('admin@example.com', admin: true);
        $victim = $this->createUser('victim@example.com');
        $victimId = $victim->getId();
        $this->client->loginUser($admin);

        $this->client->request('POST', '/admin/users/'.$victimId.'/delete', [
            '_token' => 'not-a-real-token',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNotNull($this->em->find(User::class, $victimId));
    }

    public function testRegularUserCannotAccessAdminUserList(): void
    {
        $regular = $this->createUser('regular@example.com');
        $this->client->loginUser($regular);

        $this->client->request('GET', '/admin/users');

        // The /admin firewall requires ROLE_ADMIN.
        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND],
            "Expected 403 or redirect for non-admin on /admin/users, got $status",
        );
    }

    public function testRegularUserCannotDeleteAnyUser(): void
    {
        $regular = $this->createUser('regular@example.com');
        $other = $this->createUser('other@example.com');
        $this->client->loginUser($regular);

        $this->client->request('POST', '/admin/users/'.$other->getId().'/delete', [
            '_token' => 'irrelevant',
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND],
            "Expected 403 or redirect when non-admin posts a delete request, got $status",
        );
        $this->em->clear();
        self::assertNotNull($this->em->find(User::class, $other->getId()));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Pick a Symfony Crawler form: optionally scope by CSS selector when the page
     * has several POST forms (e.g. locale switchers before the auth form).
     *
     * @param non-empty-string|null $formSelector e.g. '#registerForm form'
     */
    private function grabFirstForm(Crawler $crawler, ?string $formSelector = null): Form
    {
        if (null !== $formSelector) {
            $node = $crawler->filter($formSelector)->first();
            self::assertGreaterThan(0, $node->count(), \sprintf('No form matched selector "%s".', $formSelector));

            return $node->form();
        }

        $node = $crawler->filter('form')->reduce(static function (Crawler $f): bool {
            $method = strtoupper((string) $f->attr('method'));

            return \in_array($method, ['POST', ''], true);
        })->first();

        self::assertGreaterThan(0, $node->count(), 'No POST form found on the page.');

        return $node->form();
    }

    private function extractUserDeleteToken(int $userId): string
    {
        $this->client->request('GET', '/admin/users');
        $body = (string) $this->client->getResponse()->getContent();

        // The admin users page renders a per-row trigger link that carries
        // the CSRF token in `data-csrf-token`; a shared modal populates the
        // form from those attributes on click.
        if (!preg_match(
            '#data-action-url="[^"]*/admin/users/'.$userId.'/delete"[\s\S]*?data-csrf-token="([^"]+)"#',
            $body,
            $m,
        )) {
            self::fail("Could not locate CSRF token for /admin/users/$userId/delete on the admin users page.");
        }

        return $m[1];
    }

    /**
     * EasyAdmin's ChoiceField uses non-scalar choices (UserRole enum cases),
     * so Symfony's ChoiceType falls back to indexing choices by position.
     * Map a UserRole back to the matching "0"/"1"/... string the form expects.
     */
    private function roleChoiceValue(UserRole $role): string
    {
        foreach (UserRole::cases() as $i => $case) {
            if ($case === $role) {
                return (string) $i;
            }
        }

        throw new LogicException('Unknown UserRole '.$role->name);
    }
}
