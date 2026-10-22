<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * The web entry points for signing in, signing up and out.
 */
final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route('/login', name: 'web_login')]
    public function login(AuthenticationUtils $utils): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('web_home');
        }

        return $this->render('security/login.html.twig', [
            'last_email' => $utils->getLastUsername(),
            'error' => $utils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'web_logout', methods: ['POST'])]
    public function logout(Request $request, CsrfTokenManagerInterface $tokens): RedirectResponse
    {
        if (!$tokens->isTokenValid(new CsrfToken('logout', $request->request->getString('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return $this->redirectToRoute('web_home');
    }
}
