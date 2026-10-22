<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\ApiException;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The web pages of the user resource for signing up.
 */
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserService $users,
        private readonly CsrfTokenManagerInterface $csrfTokens,
        private readonly Security $security,
    ) {
    }

    #[Route('/register', name: 'web_register', methods: ['GET'])]
    public function register(): Response|RedirectResponse
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('web_home');
        }

        return $this->render('security/register.html.twig');
    }

    /**
     * Creates an account and signs the new user in.
     */
    #[Route('/register', name: 'web_user_create', methods: ['POST'])]
    public function store(Request $request): RedirectResponse
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('web_home');
        }

        if (!$this->csrfTokens->isTokenValid(new CsrfToken('register', $request->request->getString('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $username = trim($request->request->getString('username'));
        $email = trim($request->request->getString('email'));
        $password = $request->request->getString('password');

        $errors = [];
        if ('' === $username) {
            $errors[] = 'username is required';
        }
        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email is required';
        }
        if (strlen($password) < UserService::PASSWORD_MIN_LENGTH) {
            $errors[] = 'password is too short (minimum is '.UserService::PASSWORD_MIN_LENGTH.' characters)';
        }

        if ([] === $errors && null !== $user = $this->tryRegister($username, $email, $password)) {
            $this->security->login($user, null, 'main');

            return $this->redirectToRoute('web_home');
        }

        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('web_register');
    }

    /**
     * Wraps the registration errors into flash messages.
     */
    private function tryRegister(string $username, string $email, string $password): ?User
    {
        try {
            return $this->users->register($username, $email, $password);
        } catch (ApiException $exception) {
            foreach ($exception->getErrors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addFlash('error', $field.' '.$message);
                }
            }

            return null;
        }
    }
}
