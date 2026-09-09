<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Guards the /api firewall with a single shared secret sent as the X-API-KEY header.
 *
 * The client (the Telegram bot) is not a person and has no row in the database,
 * so there is nothing to look up — a valid key is the whole proof of identity.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private const string HEADER = 'X-API-KEY';

    public function __construct(
        #[Autowire('%env(API_KEY)%')] private readonly string $apiKey,
    ) {
    }

    /**
     * Should this authenticator handle the request?
     *
     * Always true: the firewall's `pattern: ^/api` already limits us to API routes,
     * and on those routes a missing key must be a 401 — not "skip auth and fall through".
     */
    public function supports(Request $request): ?bool
    {
        return true;
    }

    /**
     * Turn the request into a Passport, or throw an AuthenticationException.
     */
    public function authenticate(Request $request): Passport
    {
        $providedKey = $request->headers->get(self::HEADER);

        if ('' === $this->apiKey || null === $providedKey || !hash_equals($this->apiKey, $providedKey)) {
            throw new CustomUserMessageAuthenticationException('Missing or invalid API key.');
        }

        return new SelfValidatingPassport(
            new UserBadge('api-client', static fn (): InMemoryUser => new InMemoryUser('api-client', null, ['ROLE_API'])),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // null = "nothing to do, let the request reach the controller"
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Authentication failed'], Response::HTTP_UNAUTHORIZED);
    }
}
