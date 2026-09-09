<?php

namespace App\Controller\Api;

use App\Repository\DriverRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DriverSearchController extends AbstractController
{
    public function __construct(private DriverRepository $driverRepository)
    {
    }

    #[Route('/api/drivers/search', name: 'api_drivers_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $query = (string) $request->query->get('query', '');

        return $this->json($this->driverRepository->search($query), context: [
            'groups' => ['driver:list'],
        ]);
    }
}