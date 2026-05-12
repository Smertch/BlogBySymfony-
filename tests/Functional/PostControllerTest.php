<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\PostLike;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end coverage for the public feed controller:
 * post create / update / delete and comment create / delete.
 *
 * The tests boot the full Symfony kernel against the dedicated
 * `blog_test` Postgres database (configured via Doctrine's
 * dbname_suffix=_test in the test environment).
 */
final class PostControllerTest extends FunctionalTestCase
{
    public function testGuestCannotCreatePostAndIsRedirectedToLogin(): void
    {
        // No login: posting to /posts must bounce to the login form.
        $this->client->request('POST', '/posts', [
            '_token' => 'irrelevant',
            'title' => 'Anonymous post',
            'content' => 'Should not be saved.',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame(0, $this->em->getRepository(Post::class)->count([]));
    }

    public function testAuthenticatedUserCanCreatePost(): void
    {
        $user = $this->createUser('alice@example.com');
        $this->client->loginUser($user);

        $token = $this->extractToken('post_create');
        $this->client->request('POST', '/posts', [
            '_token' => $token,
            'title' => 'Hello world',
            'content' => "Line 1\nLine 2",
        ]);

        self::assertResponseRedirects();
        $post = $this->em->getRepository(Post::class)->findOneBy(['title' => 'Hello world']);
        self::assertNotNull($post, 'New post should be persisted.');
        self::assertSame("Line 1\nLine 2", $post->getContent());
        self::assertSame($user->getId(), $post->getUser()?->getId());
    }

    public function testCreatePostWithEmptyTitleIsRejected(): void
    {
        $user = $this->createUser('alice@example.com');
        $this->client->loginUser($user);
        $token = $this->extractToken('post_create');

        $this->client->request('POST', '/posts', [
            '_token' => $token,
            'title' => '   ',
            'content' => 'Body without a title.',
        ]);

        self::assertResponseRedirects('/');
        self::assertSame(0, $this->em->getRepository(Post::class)->count([]));
    }

    public function testCreatePostWithInvalidCsrfTokenIsRejected(): void
    {
        $user = $this->createUser('alice@example.com');
        $this->client->loginUser($user);

        $this->client->request('POST', '/posts', [
            '_token' => 'not-a-real-token',
            'title' => 'Sneaky',
            'content' => 'CSRF bypass attempt.',
        ]);

        self::assertResponseRedirects('/');
        self::assertSame(0, $this->em->getRepository(Post::class)->count([]));
    }

    public function testAuthorCanUpdateOwnPost(): void
    {
        $user = $this->createUser('alice@example.com');
        $post = $this->createPost($user, 'Original title', 'Original body');
        $this->client->loginUser($user);

        $token = $this->extractEditToken($post->getId());
        $this->client->request('POST', '/posts/'.$post->getId(), [
            '_method' => 'PUT',
            '_token' => $token,
            'title' => 'Updated title',
            'content' => 'Updated body',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $reloaded = $this->em->find(Post::class, $post->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Updated title', $reloaded->getTitle());
        self::assertSame('Updated body', $reloaded->getContent());
    }

    public function testNonOwnerCannotUpdateAnotherUsersPost(): void
    {
        $author = $this->createUser('alice@example.com');
        $stranger = $this->createUser('bob@example.com');
        $post = $this->createPost($author, 'Authored by Alice', 'Body');

        $this->client->loginUser($stranger);
        // Ownership is checked before CSRF, so the controller answers 403
        // even without a real CSRF token.
        $this->client->request('POST', '/posts/'.$post->getId(), [
            '_method' => 'PUT',
            '_token' => 'irrelevant',
            'title' => 'Hijacked',
            'content' => 'Should not save.',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        $this->em->clear();
        $reloaded = $this->em->find(Post::class, $post->getId());
        self::assertSame('Authored by Alice', $reloaded?->getTitle());
    }

    public function testAdminCanUpdateAnyPost(): void
    {
        $author = $this->createUser('alice@example.com');
        $admin = $this->createUser('admin@example.com', admin: true);
        $post = $this->createPost($author, 'Authored by Alice', 'Body');

        $this->client->loginUser($admin);
        $token = $this->extractEditToken($post->getId());

        $this->client->request('POST', '/posts/'.$post->getId(), [
            '_method' => 'PUT',
            '_token' => $token,
            'title' => 'Moderated',
            'content' => 'Edited by admin.',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $reloaded = $this->em->find(Post::class, $post->getId());
        self::assertSame('Moderated', $reloaded?->getTitle());
    }

    public function testAuthorCanDeleteOwnPost(): void
    {
        $user = $this->createUser('alice@example.com');
        $post = $this->createPost($user, 'To remove', 'Body');
        $postId = $post->getId();
        $this->client->loginUser($user);

        $token = $this->extractRemoveToken($postId);
        $this->client->request('POST', '/posts/'.$postId, [
            '_method' => 'DELETE',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/');
        $this->em->clear();
        self::assertNull($this->em->find(Post::class, $postId));
    }

    public function testNonOwnerCannotDeletePost(): void
    {
        $author = $this->createUser('alice@example.com');
        $stranger = $this->createUser('bob@example.com');
        $post = $this->createPost($author, 'Protected', 'Body');

        $this->client->loginUser($stranger);
        $this->client->request('POST', '/posts/'.$post->getId(), [
            '_method' => 'DELETE',
            '_token' => 'irrelevant',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        $this->em->clear();
        self::assertNotNull($this->em->find(Post::class, $post->getId()));
    }

    public function testAdminCanDeleteAnyPost(): void
    {
        $author = $this->createUser('alice@example.com');
        $admin = $this->createUser('admin@example.com', admin: true);
        $post = $this->createPost($author, 'Authored by Alice', 'Body');
        $postId = $post->getId();

        $this->client->loginUser($admin);
        $token = $this->extractRemoveToken($postId);

        $this->client->request('POST', '/posts/'.$postId, [
            '_method' => 'DELETE',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/');
        $this->em->clear();
        self::assertNull($this->em->find(Post::class, $postId));
    }

    public function testDeletingPostAlsoRemovesItsCommentsAndLikes(): void
    {
        $author = $this->createUser('alice@example.com');
        $commenter = $this->createUser('bob@example.com');
        $post = $this->createPost($author, 'Will cascade', 'Body');

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUser($commenter);
        $comment->setBody('First!');
        $this->em->persist($comment);

        $like = new PostLike();
        $like->setPost($post);
        $like->setUser($commenter);
        $this->em->persist($like);
        $this->em->flush();

        $this->client->loginUser($author);
        $token = $this->extractRemoveToken($post->getId());
        $this->client->request('POST', '/posts/'.$post->getId(), [
            '_method' => 'DELETE',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/');
        $this->em->clear();
        self::assertSame(0, $this->em->getRepository(Post::class)->count([]));
        self::assertSame(0, $this->em->getRepository(Comment::class)->count([]));
        self::assertSame(0, $this->em->getRepository(PostLike::class)->count([]));
    }

    public function testAuthenticatedUserCanAddCommentToAnyPost(): void
    {
        $author = $this->createUser('alice@example.com');
        $commenter = $this->createUser('bob@example.com');
        $post = $this->createPost($author, 'Public discussion', 'Body');

        $this->client->loginUser($commenter);
        $token = $this->extractCommentToken($post->getId());

        $this->client->request('POST', '/posts/'.$post->getId().'/comments', [
            '_token' => $token,
            'body' => 'Great article!',
        ]);

        self::assertResponseRedirects();
        $comments = $this->em->getRepository(Comment::class)->findAll();
        self::assertCount(1, $comments);
        self::assertSame('Great article!', $comments[0]->getBody());
        self::assertSame($commenter->getId(), $comments[0]->getUser()?->getId());
        self::assertSame($post->getId(), $comments[0]->getPost()?->getId());
    }

    public function testCommentWithEmptyBodyIsRejected(): void
    {
        $author = $this->createUser('alice@example.com');
        $post = $this->createPost($author, 'Title', 'Body');

        $this->client->loginUser($author);
        $token = $this->extractCommentToken($post->getId());

        $this->client->request('POST', '/posts/'.$post->getId().'/comments', [
            '_token' => $token,
            'body' => '   ',
        ]);

        self::assertResponseRedirects();
        self::assertSame(0, $this->em->getRepository(Comment::class)->count([]));
    }

    public function testCommentAuthorCanDeleteOwnComment(): void
    {
        $author = $this->createUser('alice@example.com');
        $commenter = $this->createUser('bob@example.com');
        $post = $this->createPost($author, 'Title', 'Body');

        $comment = $this->createComment($post, $commenter, 'My comment');
        $commentId = $comment->getId();

        $this->client->loginUser($commenter);
        $token = $this->extractCommentDeleteToken($commentId);

        $this->client->request('POST', '/comments/'.$commentId, [
            '_method' => 'DELETE',
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->find(Comment::class, $commentId));
    }

    public function testNonAuthorCannotDeleteSomeoneElsesComment(): void
    {
        $author = $this->createUser('alice@example.com');
        $commenter = $this->createUser('bob@example.com');
        $intruder = $this->createUser('eve@example.com');
        $post = $this->createPost($author, 'Title', 'Body');
        $comment = $this->createComment($post, $commenter, 'Genuine comment');

        $this->client->loginUser($intruder);
        $this->client->request('POST', '/comments/'.$comment->getId(), [
            '_method' => 'DELETE',
            '_token' => 'irrelevant',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        $this->em->clear();
        self::assertNotNull($this->em->find(Comment::class, $comment->getId()));
    }

    public function testAdminCanDeleteAnyComment(): void
    {
        $author = $this->createUser('alice@example.com');
        $commenter = $this->createUser('bob@example.com');
        $admin = $this->createUser('admin@example.com', admin: true);
        $post = $this->createPost($author, 'Title', 'Body');
        $comment = $this->createComment($post, $commenter, 'Inappropriate');
        $commentId = $comment->getId();

        $this->client->loginUser($admin);
        $token = $this->extractCommentDeleteToken($commentId);

        $this->client->request('POST', '/comments/'.$commentId, [
            '_method' => 'DELETE',
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->find(Comment::class, $commentId));
    }

    /**
     * Read a server-rendered CSRF token from the home page. The page must
     * already contain a form that emits the requested token.
     */
    private function extractToken(string $tokenId): string
    {
        $body = $this->renderHome();

        return match ($tokenId) {
            'post_create' => $this->grabFirstToken($body, '/id="createPostModal".*?name="_token" value="([^"]+)"/s'),
            default => throw new LogicException('Unsupported token id: '.$tokenId),
        };
    }

    private function extractEditToken(int $postId): string
    {
        return $this->grabFirstToken(
            $this->renderHome(),
            '/data-post-id="'.$postId.'"[\s\S]*?data-edit-token="([^"]+)"/'
        );
    }

    private function extractRemoveToken(int $postId): string
    {
        return $this->grabFirstToken(
            $this->renderHome(),
            '#action="/posts/'.$postId.'"[\s\S]*?_method"\s+value="DELETE"[\s\S]*?name="_token"\s+value="([^"]+)"#'
        );
    }

    private function extractCommentToken(int $postId): string
    {
        return $this->grabFirstToken(
            $this->renderHome(),
            '#action="/posts/'.$postId.'/comments"[\s\S]*?name="_token"\s+value="([^"]+)"#'
        );
    }

    private function extractCommentDeleteToken(int $commentId): string
    {
        return $this->grabFirstToken(
            $this->renderHome(),
            '#action="/comments/'.$commentId.'"[\s\S]*?name="_token"\s+value="([^"]+)"#'
        );
    }

    /**
     * Render the feed page after clearing Doctrine's identity map so the
     * controller fetches fresh entities (including the comments / likes
     * collections created via the test helpers).
     */
    private function renderHome(): string
    {
        $this->em->clear();
        $this->client->request('GET', '/');

        return (string) $this->client->getResponse()->getContent();
    }

    private function grabFirstToken(string $haystack, string $pattern): string
    {
        if (!preg_match($pattern, $haystack, $m)) {
            // Dump a localized window around the page section that's missing
            // the token so the failure message is actionable.
            $snippet = $haystack;
            if (preg_match('#(/comments/\d+|/posts/\d+)#', $pattern, $hint)
                && false !== ($pos = strpos($haystack, $hint[1]))
            ) {
                $snippet = substr($haystack, max(0, $pos - 100), 600);
            }
            self::fail("CSRF token not found via pattern: $pattern\n----\n$snippet");
        }

        return $m[1];
    }
}
