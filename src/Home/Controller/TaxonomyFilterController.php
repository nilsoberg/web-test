<?php

namespace App\Home\Controller;

use App\Service\TaxonomyReferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TaxonomyFilterController extends AbstractController
{
    #[Route('/api/taxonomy_filter', name: 'api_taxonomy_autocomplete')]
    public function autocomplete(Request $request, TaxonomyReferenceService $taxService): JsonResponse
    {
        $rank = mb_strtolower($request->query->get('rank', ''));
        $query = mb_strtolower($request->query->get('q', ''));

        // Basic validation
        if (strlen($query) < 3 || !in_array($rank, $taxService->getSearchableFields())) {
            return new JsonResponse([]);
        }

        // Use the custom repository method to find matching rank values
        $rankResults = $taxService->searchAutocomplete($rank, $query);

        $formatted = array_map(function ($row) {
            return ['value' => $row['rank_value']];
        }, $rankResults);

        return new JsonResponse($formatted);
    }
}
