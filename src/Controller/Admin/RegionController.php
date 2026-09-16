<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\LoadMorePaginator;
use App\Entity\Region;
use App\Form\RegionType;
use App\Repository\RegionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/regions')]
#[IsGranted('ROLE_ADMIN')]
final class RegionController extends AbstractController
{
    #[Route('', name: 'admin_region_index', methods: ['GET'])]
    public function index(Request $request, RegionRepository $regions, LoadMorePaginator $paginator): Response
    {
        return $this->render('admin/region/index.html.twig', [
            'page' => $paginator->paginate($regions, $request, ['code' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_region_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $region = new Region();
        $form = $this->createForm(RegionType::class, $region);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($region);
            $em->flush();
            $this->addFlash('success', sprintf('Region "%s" created.', $region->getCode()));

            return $this->redirectToRoute('admin_region_index');
        }

        return $this->render('admin/region/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_region_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Region $region, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RegionType::class, $region);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); // $region is already managed — no persist() needed
            $this->addFlash('success', sprintf('Region "%s" updated.', $region->getCode()));

            return $this->redirectToRoute('admin_region_index');
        }

        return $this->render('admin/region/edit.html.twig', [
            'region' => $region,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_region_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Region $region, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-region-' . $region->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($region);
            $em->flush();
            $this->addFlash('success', 'Region deleted.');
        }

        return $this->redirectToRoute('admin_region_index');
    }
}
