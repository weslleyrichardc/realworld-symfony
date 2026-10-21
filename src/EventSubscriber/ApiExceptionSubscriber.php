<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\ApiException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Renders every failure below `/api` with the error envelope of the RealWorld
 * specification.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onException')]
final class ApiExceptionSubscriber
{
    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof ApiException) {
            $event->setResponse(new JsonResponse(
                ['errors' => $exception->getErrors()],
                $exception->getStatusCode(),
            ));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse(
                ['errors' => ['body' => [$this->messageFor($exception->getStatusCode())]]],
                $exception->getStatusCode(),
                $exception->getHeaders(),
            ));
        }
    }

    private function messageFor(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'is missing',
            403 => 'forbidden',
            404 => 'not found',
            405 => 'method not allowed',
            default => 'error',
        };
    }
}
