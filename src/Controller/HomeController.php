<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\TagRepository;
use App\Service\Viewer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The home page with the global feed, the personal feed and the tag filters.
 */
final class HomeController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly TagRepository $tags,
        private readonly Viewer $viewer,
    ) {
    }

    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $tag = trim($request->query->getString('tag'));
        $tag = '' === $tag ? null : $tag;

        $user = $this->viewer->user();
        $personalFeed = 'yours' === $request->query->getString('feed') && null !== $user;

        if ($personalFeed) {
            $articles = $this->articles->findFeed($user, self::PAGE_SIZE, $offset);
            $count = $this->articles->countFeed($user);
        } else {
            $articles = $this->articles->findFiltered($tag, null, null, self::PAGE_SIZE, $offset);
            $count = $this->articles->countFiltered($tag, null, null);
        }

        return $this->render('home/index.html.twig', [
            'postRows' => $this->rows($articles),
            'tagNames' => $this->tags->findUsedNames(),
            'activeTag' => $tag,
            'personalFeed' => $personalFeed,
            'page' => $page,
            'pages' => (int) ceil($count / self::PAGE_SIZE),
        ]);
    }

    /**
     * Packs each article with the viewer specific information used by the
     * preview template.
     *
     * @param list<\App\Entity\Article> $articles
     *
     * @return list<array{article: \App\Entity\Article, favorited: bool}>
     */
    private function rows(array $articles): array
    {
        $favoritedIds = $this->viewer->favoritedIds($articles);

        $rows = [];
        foreach ($articles as $article) {
            $rows[] = [
                'article' => $article,
                'favorited' => isset($favoritedIds[$article->getId()->toRfc4122()]),
            ];
        }

        return $rows;
    }
}
