<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DriverHistoryEntry;
use App\Entity\User;
use App\Form\DriverHistoryEntryType;
use App\Repository\DriverHistoryEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/driver-history')]
#[IsGranted('ROLE_ADMIN')]
final class DriverHistoryController extends AbstractController
{
    #[Route('', name: 'admin_driver_history_entry_index', methods: ['GET'])]
    public function index(DriverHistoryEntryRepository $driverHistoryEntryRepository): Response
    {
        return $this->render('admin/driver_history_entry/index.html.twig', [
            'entries' => $driverHistoryEntryRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_driver_history_entry_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $driverHistoryEntry = new DriverHistoryEntry();

        $user = $this->getUser();
        \assert($user instanceof User);
        $driverHistoryEntry->setReporter($user);

        $form = $this->createForm(DriverHistoryEntryType::class, $driverHistoryEntry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($driverHistoryEntry);
            $em->flush();
            $this->addFlash('success', sprintf('Driver history entry "%s" created.', $driverHistoryEntry->getText()));

            return $this->redirectToRoute('admin_driver_history_entry_index');
        }

        return $this->render('admin/driver_history_entry/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_driver_history_entry_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DriverHistoryEntry $driverHistoryEntry, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DriverHistoryEntryType::class, $driverHistoryEntry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); // $driver is already managed — no persist() needed
            $this->addFlash('success', sprintf('Driver history "%s" updated.', $driverHistoryEntry->getDriver()->getFirstName()));

            return $this->redirectToRoute('admin_driver_history_entry_index');
        }

        return $this->render('admin/driver_history_entry/edit.html.twig', [
            'driverHistoryEntry' => $driverHistoryEntry,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_driver_history_entry_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DriverHistoryEntry $driverHistoryEntry, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-driver-history-entry-' . $driverHistoryEntry->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($driverHistoryEntry);
            $em->flush();
            $this->addFlash('success', 'Driver history entry deleted.');
        }

        return $this->redirectToRoute('admin_driver_history_entry_index');
    }
}
