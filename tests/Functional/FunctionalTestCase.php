<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Shared scaffolding for functional tests:
 *  - boots a fresh test client and Doctrine EM for every test method,
 *  - wipes the `blog_test` database between tests for full isolation,
 *  - exposes small factories so individual tests stay focused on
 *    behaviour rather than fixture wiring.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $this->client = static::createClient(['environment' => 'test', 'debug' => false]);

        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);

        $this->wipeDatabase();
    }

    protected function createUser(string $email, bool $admin = false, string $password = 'secret'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName(ucfirst(strstr($email, '@', true) ?: 'User'));
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setRole($admin ? UserRole::Admin : UserRole::User);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createPost(User $author, string $title, string $content): Post
    {
        $post = new Post();
        $post->setTitle($title);
        $post->setContent($content);
        $post->setUser($author);

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    protected function createComment(Post $post, User $author, string $body): Comment
    {
        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUser($author);
        $comment->setBody($body);

        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }

    /**
     * Truncate all rows in the application tables and reset sequences so the
     * next test gets a clean database. CASCADE handles FK dependencies.
     */
    protected function wipeDatabase(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement(
            'TRUNCATE TABLE comments, post_likes, posts, password_reset_tokens, users RESTART IDENTITY CASCADE'
        );
    }
}
