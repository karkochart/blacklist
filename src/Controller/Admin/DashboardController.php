<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\BlacklistEntryRepository;
use App\Repository\DriverRepository;
use App\Repository\RegionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        DriverRepository $drivers,
        BlacklistEntryRepository $blacklistEntries,
        RegionRepository $regions,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'counts' => [
                'drivers' => $drivers->count([]),
                'blacklist_entries' => $blacklistEntries->count([]),
                'regions' => $regions->count([]),
            ],
        ]);
    }
}
