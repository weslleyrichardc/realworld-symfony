<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TagRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoint of the tag resource.
 */
final class TagController
{
    public function __construct(private readonly TagRepository $tags)
    {
    }

    /**
     * Lists the tags that are still attached to an article.
     */
    #[Route('/api/tags', name: 'api_tag_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['tags' => $this->tags->findUsedNames()]);
    }
}
