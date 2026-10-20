<?php

namespace App\EST\Controller;

use App\Service\DatabaseListService;
use App\Service\FamilyInformationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class FamilyInformationController extends AbstractController
{
    #[Route('/efi-est/family_list', name: 'family_list', methods: ['GET'])]
    public function family_list(
        DatabaseListService $databaseService,
        FamilyInformationService $infoService,
        Request $request,
    ): Response
    {
        $search = $request->query->get('search', '');
        $filter = $request->query->get('filter', 'PF');
        $showAll = $request->query->get('show', '');
        $downloadList = $request->query->get('download', '');
        $databaseId = $request->query->get('database-id');

        $search = preg_replace('/[^a-zA-Z0-9\-_]/', '', $search);
        $showAll = $showAll === 'all';
        $prepareDownload = $downloadList === 'download';
        $returnClanMapping = $filter === 'CLPF';

        // Look up the specified database or get the default database
        if ($databaseId !== null) {
            $databaseId = (int)$databaseId;
            $database = $databaseService->getDatabase($databaseId);
        } else {
            $database = $databaseService->getDefaultDatabase();
            $databaseId = $database?->getId();
        }

        // Database will be null if the ID is invalid, or if the user isn't authorized
        // to view the selected database
        if (!$database) {
            $families = [];
        } else {
            if ($returnClanMapping) {
                $families = $infoService->getPfamClanMapping($search, $showAll, $databaseId);
            } else {
                $families = $infoService->getFamilyList($search, $filter, $showAll, $databaseId);
            }
        }

        $databases = array_map(function ($db) use ($databaseId) {
            return ['id' => $db->getId(), 'publicName' => $db->getPublicName(), 'selected' => $db->getId() === $databaseId];
        }, $databaseService->getActiveDatabases());

        if ($prepareDownload) {
            return $this->prepareDownload($families, $returnClanMapping);
        } else {
            return $this->render('efi-est/family_info.html.twig', [
                'families'      => $families,
                'databases'     => $databases,
                'currentSearch' => $search,
                'currentFilter' => $filter,
                'showAll'       => $showAll,
            ]);
        }
    }

    private function prepareDownload(array $families, bool $clanMapping): StreamedResponse
    {
        if ($clanMapping) {
            $preparedData = [ ['clan_id', 'clan_name', 'clan_uniprot_size', 'clan_uniref90_size', 'clan_uniref50_size', 'member_id', 'member_uniprot_size', 'member_uniref90_size', 'member_uniref50_size'] ];
            foreach ($families as $clan) {
                $clanRow = [$clan['id'], $clan['name'], $clan['size'], $clan['u90'], $clan['u50']];
                foreach ($clan['members'] as $member) {
                    $preparedData[] = array_merge($clanRow, [ $member['id'], $member['size'], $member['u90'], $member['u50'] ]);
                }
            }
        } else {
            $preparedData = [ ['family', 'short_name', 'uniprot_size', 'uniref90_size', 'uniref50_size'] ];
            foreach ($families as $family) {
                $preparedData[] = [$family['family'], $family['short_name'], $family['num_members'], $family['num_uniref90_members'], $family['num_uniref50_members']];
            }
        }

        $response = new StreamedResponse(function () use ($preparedData) {
            $output = fopen('php://output', 'w');

            foreach ($preparedData as $row) {
                fwrite($output, implode("\t", $row) . "\n");
            }
            fclose($output);
        });

        $filename = "EFI_family_information.tab";

        $response->headers->set('Content-Type', 'text/tab-separated-values');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
