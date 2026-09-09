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
 * Guards ^/api/telegram. Telegram sends the value configured via setWebhook(secret_token=...)
 * back as the "X-Telegram-Bot-Api-Secret-Token" header on every webhook call.
 */
final class TelegramWebhookAuthenticator extends AbstractAuthenticator
{
    private const string HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    public function __construct(
        #[Autowire('%env(TELEGRAM_WEBHOOK_SECRET)%')] private readonly string $secret,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $provided = $request->headers->get(self::HEADER);

        if ('' === $this->secret || null === $provided || !hash_equals($this->secret, $provided)) {
            throw new CustomUserMessageAuthenticationException('Invalid Telegram webhook secret.');
        }

        return new SelfValidatingPassport(
            new UserBadge('telegram-webhook', static fn (): InMemoryUser => new InMemoryUser('telegram-webhook', null, ['ROLE_TELEGRAM'])),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Authentication failed'], Response::HTTP_UNAUTHORIZED);
    }
}
