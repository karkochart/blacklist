<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\LoadMorePaginator;
use App\Entity\TelegramUser;
use App\Entity\User;
use App\Enum\SubscriptionType;
use App\Repository\SubscriptionRepository;
use App\Repository\TelegramUserRepository;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
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
    public function index(Request $request, TelegramUserRepository $telegramUsers, SubscriptionRepository $subscriptions, LoadMorePaginator $paginator): Response
    {
        $page = $paginator->paginate($telegramUsers, $request, ['id' => 'DESC']);

        $rows = [];
        foreach ($page->items as $telegramUser) {
            $subscription = $subscriptions->latest($telegramUser);
            $rows[] = [
                'user' => $telegramUser,
                'subscription' => $subscription,
                'active' => $subscriptions->hasActiveSubscription($telegramUser),
            ];
        }

        return $this->render('admin/telegram_user/index.html.twig', [
            'rows' => $rows,
            'page' => $page,
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

    /**
     * Manual correction of the latest grant's expiry — for the odd typo, refund,
     * or complaint. Everyday top-ups go through grant() above instead.
     */
    #[Route('/{id}/set-expiry', name: 'admin_telegram_user_set_expiry', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setExpiry(Request $request, TelegramUser $telegramUser, SubscriptionRepository $subscriptions, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('set-expiry-' . $telegramUser->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('admin_telegram_user_index');
        }

        $subscription = $subscriptions->latest($telegramUser);
        if ($subscription === null) {
            $this->addFlash('error', sprintf('%s has no subscription to correct yet — grant one first.', $telegramUser->getDisplayName()));

            return $this->redirectToRoute('admin_telegram_user_index');
        }

        $raw = $request->getPayload()->getString('expiresAt');
        $expiresAt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw) ?: null;
        if ($expiresAt === null) {
            $this->addFlash('error', 'Invalid date.');

            return $this->redirectToRoute('admin_telegram_user_index');
        }

        $subscription->setExpiresAt($expiresAt);
        $em->flush();

        $this->addFlash('success', sprintf(
            'Дату підписки для %s змінено на %s.',
            $telegramUser->getDisplayName(),
            $expiresAt->format('Y-m-d H:i'),
        ));

        return $this->redirectToRoute('admin_telegram_user_index');
    }
}
