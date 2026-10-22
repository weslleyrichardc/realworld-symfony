<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Twig\Environment;

/**
 * Renders the branded error page of the web user interface.
 *
 * It is wired through `framework.error_controller` in the `prod` environment
 * only, so that the development environment keeps the full debug page.
 */
#[AsController]
final class WebErrorController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function show(Request $request, \Throwable $exception): Response
    {
        $code = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        $content = $this->twig->render('bundles/TwigBundle/Exception/error.html.twig', [
            'status_code' => $code,
            'status_text' => Response::$statusTexts[$code] ?? 'Error',
        ]);

        return new Response($content, $code);
    }
}
