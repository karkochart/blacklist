<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TelegramUser;
use App\Entity\User;
use App\Enum\SubscriptionType;
use App\Repository\SubscriptionRepository;
use App\Repository\TelegramUserRepository;
use App\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/telegram-users')]
#[IsGranted('ROLE_ADMIN')]
final class TelegramUserController extends AbstractController
{
    #[Route('', name: 'admin_telegram_user_index', methods: ['GET'])]
    public function index(TelegramUserRepository $telegramUsers, SubscriptionRepository $subscriptions): Response
    {
        $rows = [];
        foreach ($telegramUsers->findBy([], ['id' => 'DESC'], 200) as $telegramUser) {
            $rows[] = [
                'user' => $telegramUser,
                'expiresAt' => $subscriptions->latestExpiry($telegramUser),
                'active' => $subscriptions->hasActiveSubscription($telegramUser),
            ];
        }

        return $this->render('admin/telegram_user/index.html.twig', [
            'rows' => $rows,
            'types' => SubscriptionType::cases(),
        ]);
    }

    #[Route('/{id}/grant', name: 'admin_telegram_user_grant', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function grant(Request $request, TelegramUser $telegramUser, SubscriptionService $subscriptions): Response
    {
        if (!$this->isCsrfTokenValid('grant-subscription-' . $telegramUser->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('admin_telegram_user_index');
        }

        $type = SubscriptionType::tryFrom($request->getPayload()->getString('type'));
        if ($type === null) {
            $this->addFlash('error', 'Invalid subscription type.');

            return $this->redirectToRoute('admin_telegram_user_index');
        }

        $admin = $this->getUser();
        \assert($admin instanceof User);

        $subscription = $subscriptions->grant($telegramUser, $type, $admin);

        $this->addFlash('success', sprintf(
            '%s підписка для %s: до %s.',
            $type->label(),
            $telegramUser->getDisplayName(),
            $subscription->getExpiresAt()->format('Y-m-d H:i'),
        ));

        return $this->redirectToRoute('admin_telegram_user_index');
    }
}
