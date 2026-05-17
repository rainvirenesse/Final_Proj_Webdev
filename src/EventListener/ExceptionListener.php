<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ExceptionListener implements EventSubscriberInterface
{
    private RouterInterface $router;
    private RequestStack $requestStack;

    public function __construct(
        RouterInterface $router,
        RequestStack $requestStack
    ) {
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after Security / HttpKernel handlers so we can replace login redirects with JSON for /api.
            KernelEvents::EXCEPTION => ['onKernelException', -250],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;

        // Handle Access Denied Exceptions
        if ($exception instanceof AccessDeniedException) {
            if ($this->isApiRequest($request)) {
                $user = $request->getUser();
                $status = $user ? Response::HTTP_FORBIDDEN : Response::HTTP_UNAUTHORIZED;
                $body = ['error' => $user ? 'Access denied' : 'Authentication required'];
                $event->setResponse(new JsonResponse($body, $status));
                return;
            }

            $message = $exception->getMessage() ?: 'Access denied. You do not have permission to access this resource.';
            
            // Check if user is authenticated
            $user = $request->getUser();
            if (!$user) {
                // Not authenticated - redirect to login
                if ($session) {
                    $session->getFlashBag()->add('warning', 'Please log in to access this page.');
                }
                $response = new RedirectResponse($this->router->generate('login'));
            } else {
                // Authenticated but insufficient permissions
                if ($session) {
                    $session->getFlashBag()->add('danger', $message);
                }
                
                // Try to redirect to previous page or safe fallback
                $referer = $request->headers->get('referer');
                if ($referer && $request->getHost() === parse_url($referer, PHP_URL_HOST)) {
                    $response = new RedirectResponse($referer);
                } else {
                    // Redirect based on user role
                    $roles = $user->getRoles();
                    if (in_array('ROLE_ADMIN', $roles)) {
                        $response = new RedirectResponse($this->router->generate('admin_dashboard'));
                    } elseif (in_array('ROLE_STAFF', $roles)) {
                        $response = new RedirectResponse($this->router->generate('admin_dashboard'));
                    } else {
                        $response = new RedirectResponse($this->router->generate('profile_show'));
                    }
                }
            }
            
            $event->setResponse($response);
            return;
        }

        // Handle Authentication Exceptions
        if ($exception instanceof AuthenticationException) {
            if ($this->isApiRequest($request)) {
                $event->setResponse(new JsonResponse(
                    ['error' => 'Authentication required'],
                    Response::HTTP_UNAUTHORIZED
                ));
                return;
            }

            if ($session) {
                $session->getFlashBag()->add('warning', 'Authentication required. Please log in.');
            }
            $response = new RedirectResponse($this->router->generate('login'));
            $event->setResponse($response);
            return;
        }

        // Handle InvalidArgumentException (from our custom validations)
        if ($exception instanceof \InvalidArgumentException) {
            $message = $exception->getMessage() ?: 'Invalid operation. Please check your input.';
            if ($session) {
                $session->getFlashBag()->add('danger', $message);
            }
            
            // Stay on current page if it's a POST request (form submission)
            if ($request->isMethod('POST')) {
                $referer = $request->headers->get('referer');
                if ($referer && $request->getHost() === parse_url($referer, PHP_URL_HOST)) {
                    $response = new RedirectResponse($referer);
                    $event->setResponse($response);
                    return;
                }
            }
        }

        // Handle other common exceptions
        if ($exception instanceof \Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            if ($session) {
                $session->getFlashBag()->add('danger', 'This record already exists. Please use a unique value.');
            }
            $referer = $request->headers->get('referer');
            if ($referer && $request->getHost() === parse_url($referer, PHP_URL_HOST)) {
                $response = new RedirectResponse($referer);
                $event->setResponse($response);
                return;
            }
        }

        if ($exception instanceof \Doctrine\ORM\EntityNotFoundException) {
            if ($session) {
                $session->getFlashBag()->add('danger', 'The requested resource was not found.');
            }
            $referer = $request->headers->get('referer');
            if ($referer && $request->getHost() === parse_url($referer, PHP_URL_HOST)) {
                $response = new RedirectResponse($referer);
                $event->setResponse($response);
                return;
            }
        }
    }

    private function isApiRequest(Request $request): bool
    {
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return true;
        }

        $accept = (string) $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json');
    }
}

