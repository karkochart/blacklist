<?php

namespace App\Controller\Api;

use App\Dto\Request\DriverSearchQuery;
use App\Repository\DriverRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class DriverSearchController extends AbstractController
{
    public function __construct(private DriverRepository $driverRepository)
    {
    }

    #[Route('/api/drivers/search', name: 'api_drivers_search', methods: ['GET'])]
    public function search(
        #[MapQueryString(validationFailedStatusCode: 422)] DriverSearchQuery $driverSearchQuery,
    ): JsonResponse {
        return $this->json(
            $this->driverRepository->search($driverSearchQuery->query, $driverSearchQuery->limit),
            context: ['groups' => ['driver:list']],
        );
    }
}