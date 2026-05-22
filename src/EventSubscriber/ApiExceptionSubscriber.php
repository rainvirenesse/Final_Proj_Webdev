<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Api\ApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Maps API exceptions to standardized JSON without exposing stack traces.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -200],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->isApiRequest($event->getRequest())) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof ValidationFailedException) {
            $errors = [];
            foreach ($throwable->getViolations() as $violation) {
                $path = $violation->getPropertyPath() ?: 'body';
                $errors[$path] = $violation->getMessage();
            }
            $event->setResponse(ApiResponse::error('Validation failed.', 422, ['violations' => $errors]));

            return;
        }

        if ($throwable instanceof \InvalidArgumentException) {
            $event->setResponse(ApiResponse::error($throwable->getMessage(), 400));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $message = $throwable->getMessage() ?: Response::$statusTexts[$status] ?? 'Error';
            $event->setResponse(ApiResponse::error($message, $status));

            return;
        }

        $event->setResponse(ApiResponse::error('An unexpected error occurred.', 500));
    }

    private function isApiRequest(Request $request): bool
    {
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return true;
        }

        return str_contains((string) $request->headers->get('Accept', ''), 'application/json');
    }
}
