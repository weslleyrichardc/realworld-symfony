<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use App\Service\Viewer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The web pages of the profile resource: the profile page with the written
 * and the favorited articles, and the follow toggle.
 */
final class ProfilePageController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly UserRepository $users,
        private readonly ArticleRepository $articles,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokens,
        private readonly Viewer $viewer,
    ) {
    }

    #[Route('/profile/{username}', name: 'web_profile_view', methods: ['GET'])]
    public function view(string $username, Request $request): Response
    {
        $profile = $this->users->findOneByUsername($username);

        if (null === $profile) {
            throw new NotFoundHttpException("Profile \"$username\" not found.");
        }

        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $favorited = 'true' === $request->query->getString('favorite');

        if ($favorited) {
            $articles = $this->articles->findFiltered(null, null, $profile->getUsername(), self::PAGE_SIZE, $offset);
            $count = $this->articles->countFiltered(null, null, $profile->getUsername());
        } else {
            $articles = $this->articles->findFiltered(null, $profile->getUsername(), null, self::PAGE_SIZE, $offset);
            $count = $this->articles->countFiltered(null, $profile->getUsername(), null);
        }

        $favoritedIds = $this->viewer->favoritedIds($articles);

        $rows = [];
        foreach ($articles as $article) {
            $rows[] = [
                'article' => $article,
                'favorited' => isset($favoritedIds[$article->getId()->toRfc4122()]),
            ];
        }

        return $this->render('profile/show.html.twig', [
            'profile' => $profile,
            'following' => $this->viewer->followedUsernames()[$profile->getUsername()] ?? false,
            'is_self' => $this->viewer->user()?->getId() === $profile->getId(),
            'favoritedTab' => $favorited,
            'postRows' => $rows,
            'page' => $page,
            'pages' => (int) ceil($count / self::PAGE_SIZE),
        ]);
    }

    /**
     * Follows, or stops following, the profile with the given username.
     */
    #[Route('/profile/{username}/follow', name: 'web_profile_follow', methods: ['POST'])]
    public function follow(string $username, Request $request): RedirectResponse
    {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken('follow'.$username, $request->request->getString('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->viewer->user();
        $profile = $this->users->findOneByUsername($username);

        if (null === $user) {
            throw $this->createAccessDeniedException('Sign in first.');
        }

        if (null === $profile) {
            throw new NotFoundHttpException("Profile \"$username\" not found.");
        }

        if (null !== $user->getId() && $user->getId() === $profile->getId()) {
            throw $this->createAccessDeniedException('You cannot follow yourself.');
        }

        if ('unfollow' === $request->request->getString('op')) {
            $user->unfollow($profile);
        } else {
            $user->follow($profile);
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('web_profile_view', ['username' => $profile->getUsername()]);
    }
}
