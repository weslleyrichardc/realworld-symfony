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
 * The settings page for updating the account of the current user.
 */
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly UserService $users,
        private readonly CsrfTokenManagerInterface $csrfTokens,
        private readonly Security $security,
    ) {
    }

    #[Route('/settings', name: 'web_settings', methods: ['GET'])]
    public function edit(): Response
    {
        return $this->render('user/settings.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    /**
     * Updates the account and refreshes the session, since a changed username
     * doubles as the security identity.
     */
    #[Route('/settings', name: 'web_settings_save', methods: ['POST'])]
    public function update(Request $request): RedirectResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Sign in first.');
        }

        if (!$this->csrfTokens->isTokenValid(new CsrfToken('settings', $request->request->getString('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $username = trim($request->request->getString('username'));
        $email = trim($request->request->getString('email'));

        $errors = [];
        if ('' === $username) {
            $errors[] = 'username is required';
        }
        if ('' === $email) {
            $errors[] = 'email is required';
        }

        $bio = trim($request->request->getString('bio'));
        $image = trim($request->request->getString('image'));

        $changes = [
            'username' => $username,
            'email' => $email,
            'bio' => '' === $bio ? null : $bio,
            'image' => '' === $image ? null : $image,
        ];

        $password = $request->request->getString('password');
        if ('' !== $password) {
            $changes['password'] = $password;
        }

        try {
            $this->users->update($user, $changes);
        } catch (ApiException $exception) {
            foreach ($exception->getErrors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addFlash('error', $field.' '.$message);
                }
            }

            $errors[] = null;
        }

        if ([] === $errors) {
            $this->security->login($user, null, 'main');
            $this->addFlash('success', 'Your settings have been saved.');
        }

        return $this->redirectToRoute('web_settings');
    }
}
