<?php declare(strict_types=1);

namespace Graphael\Security;

use Exception;
use Graphael\Exception\OmittedJwtTokenException;
use Graphael\Security\Token\JsonWebToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SecurityFacade
{
    public const ANONYMOUS_USER = 'anonymous';

    /** @var JwtFactory */
    private $jwtFactory;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var AuthenticationManagerInterface */
    private $authenticationManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager,
        JwtFactory $jwtFactory
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->jwtFactory = $jwtFactory;
    }

    public function initialize(
        Request $request,
        bool $jwtEnabled,
        ?string $usernameClaim,
        ?string $rolesClaim,
        ?string $defaultRole,
        string $adminRole
    ): void
    {
        // Disabled jwt auth means admin role for every request
        if (!$jwtEnabled) {
            $token = new JsonWebToken([$adminRole]);
            $token->setUser(static::ANONYMOUS_USER);
            $token->setAuthenticated(true);

            $this->tokenStorage->setToken($token);

            return;
        }

        try {
            if ($usernameClaim) {
                $this->jwtFactory->setUsernameClaim($usernameClaim);
            }

            if ($rolesClaim) {
                $this->jwtFactory->setRolesClaim($rolesClaim);
            }

            if ($defaultRole) {
                $this->jwtFactory->setDefaultRole($defaultRole);
            }

            $unauthenticatedToken = $this->jwtFactory->createFromRequest($request);

            $this->tokenStorage->setToken(
                $this->authenticationManager->authenticate($unauthenticatedToken)
            );
        } catch (OmittedJwtTokenException $e) {
            $token = new AnonymousToken(uniqid(), static::ANONYMOUS_USER, []);
            $token->setAuthenticated(true);

            $this->tokenStorage->setToken($token);
        } catch (Exception $e) {
            throw new AuthenticationException('Token invalid', 0, $e);
        }
    }
}
