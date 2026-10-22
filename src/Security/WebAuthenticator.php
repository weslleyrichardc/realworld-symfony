<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * The session authenticator used by the `main` firewall.
 *
 * It backs the web user interface, opposite to the stateless
 * `RealWorldJwtAuthenticator` behind `/api`, and expects a form with `email`
 * and `password` fields posted to the login page.
 */
final class WebAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'web_login';

    public function __construct(
        private readonly UserRepository $users,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->getString('email');
        $password = $request->request->getString('password');

        return new Passport(
            new UserBadge($email, fn (string $identifier): User => $this->users->findOneByEmail($identifier) ?? throw new UserNotFoundException()),
            new PasswordCredentials($password),
            [new CsrfTokenBadge('authenticate', $request->request->getString('_csrf_token'))],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ('' !== $target = (string) $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($target);
        }

        return new RedirectResponse($this->urlGenerator->generate('web_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
