<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\LoadMorePaginator;
use App\Entity\Driver;
use App\Form\DriverType;
use App\Repository\DriverRepository;
use App\Repository\RegionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/drivers')]
#[IsGranted('ROLE_ADMIN')]
final class DriverController extends AbstractController
{
    #[Route('', name: 'admin_driver_index', methods: ['GET'])]
    public function index(Request $request, DriverRepository $drivers, LoadMorePaginator $paginator): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        if ($q !== '') {
            $limit = $paginator->limitFromRequest($request);
            $page = $paginator->fromResults($drivers->search($q, $limit), $drivers->countMatching($q), $limit);
        } else {
            $page = $paginator->paginate($drivers, $request, ['id' => 'DESC']);
        }

        return $this->render('admin/driver/index.html.twig', [
            'page' => $page,
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'admin_driver_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, RegionRepository $regions): Response
    {
        $driver = new Driver();
        $driver->setRegion($regions->findOneByCode('UA-51'));
        $form = $this->createForm(DriverType::class, $driver);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($driver);
            $em->flush();
            $this->addFlash('success', sprintf('Driver "%s" created.', $driver->getFullName()));

            return $this->redirectToRoute('admin_driver_index');
        }

        return $this->render('admin/driver/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_driver_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Driver $driver, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DriverType::class, $driver);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); // $driver is already managed — no persist() needed
            $this->addFlash('success', sprintf('Driver "%s" updated.', $driver->getFullName()));

            return $this->redirectToRoute('admin_driver_index');
        }

        return $this->render('admin/driver/edit.html.twig', [
            'driver' => $driver,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_driver_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Driver $driver, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-driver-' . $driver->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($driver);
            $em->flush();
            $this->addFlash('success', 'Driver deleted.');
        }

        return $this->redirectToRoute('admin_driver_index');
    }
}
