<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\LoadMorePaginator;
use App\Entity\BlacklistEntry;
use App\Entity\User;
use App\Form\BlacklistEntryType;
use App\Repository\BlacklistEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/blacklist')]
#[IsGranted('ROLE_ADMIN')]
final class BlacklistEntryController extends AbstractController
{
    #[Route('', name: 'admin_blacklist_entry_index', methods: ['GET'])]
    public function index(Request $request, BlacklistEntryRepository $entries, LoadMorePaginator $paginator): Response
    {
        return $this->render('admin/blacklist_entry/index.html.twig', [
            'page' => $paginator->paginate($entries, $request, ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_blacklist_entry_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entry = new BlacklistEntry();

        // structured attribution: the entry is reported by whoever is logged in
        $user = $this->getUser();
        \assert($user instanceof User);
        $entry->setReporter($user);

        $form = $this->createForm(BlacklistEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entry);
            $em->flush();
            $this->addFlash('success', 'Blacklist entry created.');

            return $this->redirectToRoute('admin_blacklist_entry_index');
        }

        return $this->render('admin/blacklist_entry/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_blacklist_entry_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, BlacklistEntry $entry, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(BlacklistEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Blacklist entry updated.');

            return $this->redirectToRoute('admin_blacklist_entry_index');
        }

        return $this->render('admin/blacklist_entry/edit.html.twig', [
            'entry' => $entry,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_blacklist_entry_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, BlacklistEntry $entry, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-blacklist-entry-' . $entry->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($entry);
            $em->flush();
            $this->addFlash('success', 'Blacklist entry deleted.');
        }

        return $this->redirectToRoute('admin_blacklist_entry_index');
    }
}
