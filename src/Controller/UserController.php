<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\ApiException;
use App\Http\Payload;
use App\Presenter\UserPresenter;
use App\Service\UserService;
use App\Service\Viewer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoints of the user resource.
 *
 * @see https://github.com/gothinkster/realworld/blob/main/docs/src/content/docs/specifications/backend/endpoints.md
 */
final class UserController
{
    public function __construct(
        private readonly UserService $users,
        private readonly Viewer $viewer,
        private readonly UserPresenter $presenter,
    ) {
    }

    /**
     * Registers an account.
     */
    #[Route('/api/users', name: 'api_user_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = Payload::fromRequest($request, 'user');

        $username = $payload->requiredString('username');
        $email = $payload->requiredEmail('email');
        $password = $payload->requiredString('password');

        $this->checkPasswordPolicy($payload, $password);

        if ($payload->hasErrors()) {
            throw ApiException::unprocessable($payload->errors());
        }

        $user = $this->users->register($username, $email, $password);

        return $this->respond($user, Response::HTTP_CREATED);
    }

    /**
     * Exchanges the credentials of an account for a JWT.
     */
    #[Route('/api/users/login', name: 'api_user_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = Payload::fromRequest($request, 'user');

        $email = $payload->requiredString('email');
        $password = $payload->requiredString('password');

        if ($payload->hasErrors()) {
            throw ApiException::unprocessable($payload->errors());
        }

        $user = $this->users->authenticate($email, $password);

        return $this->respond($user);
    }

    /**
     * Returns the authenticated account.
     */
    #[Route('/api/user', name: 'api_user_current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        return $this->respond($this->requireUser());
    }

    /**
     * Updates the authenticated account.
     */
    #[Route('/api/user', name: 'api_user_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->requireUser();

        $payload = Payload::fromRequest($request, 'user');

        $changes = [];

        if ($payload->has('username')) {
            $changes['username'] = $payload->optionalString('username');
        }

        if ($payload->has('email')) {
            $changes['email'] = $payload->optionalEmail('email');
        }

        if ($payload->has('password')) {
            $password = $payload->optionalString('password');
            $this->checkPasswordPolicy($payload, $password ?? '');
            $changes['password'] = $password;
        }

        // `bio` and `image` are nullable: an empty string is normalised to
        // `null`, and an explicit `null` clears the field.
        if ($payload->has('bio')) {
            $changes['bio'] = $payload->optionalNullableString('bio');
        }

        if ($payload->has('image')) {
            $changes['image'] = $payload->optionalNullableString('image');
        }

        if ($payload->hasErrors()) {
            throw ApiException::unprocessable($payload->errors());
        }

        $this->users->update($user, $changes);

        return $this->respond($user);
    }

    /**
     * The password policy follows NIST 800-63B: at least eight characters, no
     * composition rules and no breach check.
     */
    private function checkPasswordPolicy(Payload $payload, string $password): void
    {
        if ('' === $password) {
            // The blank password has already been reported.
            return;
        }

        $length = strlen($password);

        if ($length < UserService::PASSWORD_MIN_LENGTH) {
            $payload->addError('password', 'is too short');

            return;
        }

        if ($length > UserService::PASSWORD_MAX_LENGTH) {
            $payload->addError('password', 'is too long');
        }
    }

    /**
     * @throws ApiException when the request carries no valid token
     */
    private function requireUser(): User
    {
        $user = $this->viewer->user();

        if (null === $user) {
            throw ApiException::unauthorized();
        }

        return $user;
    }

    private function respond(User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(
            ['user' => $this->presenter->present($user, $this->users->token($user))],
            $status,
        );
    }
}
