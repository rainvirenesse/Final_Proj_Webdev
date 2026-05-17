<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?RedirectResponse
    {
        $message = $accessDeniedException->getMessage() ?: 'Access denied. You do not have permission to access this resource.';
        
        // Add flash message
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session) {
            $session->getFlashBag()->add('danger', $message);
        }
        
        // Try to redirect to previous page
        $referer = $request->headers->get('referer');
        if ($referer && $request->getHost() === parse_url($referer, PHP_URL_HOST)) {
            return new RedirectResponse($referer);
        }
        
        // Redirect based on user role or to login
        $user = $request->getUser();
        if ($user) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                return new RedirectResponse($this->router->generate('admin_dashboard'));
            } elseif (in_array('ROLE_STAFF', $roles)) {
                return new RedirectResponse($this->router->generate('admin_dashboard'));
            } else {
                return new RedirectResponse($this->router->generate('profile_show'));
            }
        }
        
        return new RedirectResponse($this->router->generate('login'));
    }
}

