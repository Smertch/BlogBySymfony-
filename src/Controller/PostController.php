<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\PostLike;
use App\Entity\User;
use App\Repository\PostLikeRepository;
use App\Repository\PostRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PostController extends AbstractController
{
    private const FEED_LIMIT = 50;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostRepository $postRepository,
        private readonly PostLikeRepository $postLikeRepository,
    ) {
    }

    #[Route(path: '/', name: 'home', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $posts = $this->postRepository->feedSearch($query !== '' ? $query : null, self::FEED_LIMIT);

        return $this->render('posts/index.html.twig', [
            'posts' => $posts,
            'search' => $query,
        ]);
    }

    #[Route(path: '/posts', name: 'post_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('post_create', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('home');
        }

        $title = trim((string) $request->request->get('title', ''));
        $content = trim((string) $request->request->get('content', ''));

        if ($title === '') {
            $this->addFlash('error', 'Title cannot be empty.');

            return $this->redirectToRoute('home');
        }

        $post = new Post();
        $post->setTitle($title);
        $post->setContent($content);
        $post->setUser($this->getAppUser());

        $this->em->persist($post);
        $this->em->flush();

        $this->addFlash('success', 'Post published.');

        return $this->redirectToRoute('home', ['_fragment' => 'post-'.$post->getId()]);
    }

    #[Route(path: '/posts/{id}', name: 'post_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update(Post $post, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessOwnerOrAdmin($post);

        if (!$this->isCsrfTokenValid('post_update_'.$post->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('home');
        }

        $title = trim((string) $request->request->get('title', ''));
        $content = trim((string) $request->request->get('content', ''));

        if ($title === '') {
            $this->addFlash('error', 'Title cannot be empty.');

            return $this->redirectToRoute('home', ['_fragment' => 'post-'.$post->getId()]);
        }

        $post->setTitle($title);
        $post->setContent($content);
        $this->em->flush();

        $this->addFlash('success', 'Post updated.');

        return $this->redirectToRoute('home', ['_fragment' => 'post-'.$post->getId()]);
    }

    #[Route(path: '/posts/{id}', name: 'post_remove', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(Post $post, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessOwnerOrAdmin($post);

        if (!$this->isCsrfTokenValid('post_remove_'.$post->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('home');
        }

        $this->em->remove($post);
        $this->em->flush();

        $this->addFlash('success', 'Post removed.');

        return $this->redirectToRoute('home');
    }

    #[Route(path: '/posts/{id}/like', name: 'post_like', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function toggleLike(Post $post, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('post_like_'.$post->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('home');
        }

        $user = $this->getAppUser();
        $existing = $this->postLikeRepository->findOneByPostAndUser($post, $user);

        if ($existing !== null) {
            $this->em->remove($existing);
            $this->em->flush();

            return $this->redirectToRoute('home', ['_fragment' => 'post-'.$post->getId()]);
        }

        $like = new PostLike();
        $like->setPost($post);
        $like->setUser($user);
        $this->em->persist($like);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Concurrent like: ignore, another request already created the row.
            $this->em->clear();
        }

        return $this->redirectToRoute('home', ['_fragment' => 'post-'.$post->getId()]);
    }

    #[Route(path: '/posts/{id}/comments', name: 'post_comment_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addComment(Post $post, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('post_comment_'.$post->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('home');
        }

        $body = trim((string) $request->request->get('body', ''));
        if ($body === '') {
            $this->addFlash('error', 'Comment cannot be empty.');

            return $this->redirectToRoute('home', ['_fragment' => 'post-'.$post->getId()]);
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUser($this->getAppUser());
        $comment->setBody($body);
        $this->em->persist($comment);
        $this->em->flush();

        return $this->redirectToRoute('home', ['_fragment' => 'post-'.$post->getId()]);
    }

    #[Route(path: '/comments/{id}', name: 'comment_remove', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function deleteComment(Comment $comment, Request $request): RedirectResponse
    {
        $current = $this->getAppUser();
        $isAuthor = $comment->getUser()?->getId() === $current->getId();
        if (!$isAuthor && !$current->isAdmin()) {
            throw $this->createAccessDeniedException('You cannot delete this comment.');
        }

        if (!$this->isCsrfTokenValid('comment_delete_'.$comment->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('home');
        }

        $postId = $comment->getPost()?->getId();
        $this->em->remove($comment);
        $this->em->flush();

        $this->addFlash('success', 'Comment removed.');

        return $this->redirectToRoute('home', $postId !== null ? ['_fragment' => 'post-'.$postId] : []);
    }

    private function denyAccessUnlessOwnerOrAdmin(Post $post): void
    {
        $current = $this->getAppUser();
        $isAuthor = $post->getUser()?->getId() === $current->getId();
        if (!$isAuthor && !$current->isAdmin()) {
            throw $this->createAccessDeniedException('You cannot modify this post.');
        }
    }

    private function getAppUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $user;
    }
}
