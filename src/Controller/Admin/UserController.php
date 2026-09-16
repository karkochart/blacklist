<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\LoadMorePaginator;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $users, LoadMorePaginator $paginator): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'page' => $paginator->paginate($users, $request, ['email' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()),
            );
            $this->em->persist($user);
            $this->em->flush();
            $this->addFlash('success', sprintf('User "%s" created.', $user->getEmail()));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (\is_string($plainPassword) && '' !== $plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }
            $this->em->flush();
            $this->addFlash('success', sprintf('User "%s" updated.', $user->getEmail()));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', ['user' => $user, 'form' => $form]);
    }

    #[Route('/{id}', name: 'admin_user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'You cannot delete your own account.');

            return $this->redirectToRoute('admin_user_index');
        }

        if ($this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->getPayload()->getString('_token'))) {
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', 'User deleted.');
        }

        return $this->redirectToRoute('admin_user_index');
    }
}
