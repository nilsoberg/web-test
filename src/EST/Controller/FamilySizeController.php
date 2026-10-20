<?php

namespace App\EST\Controller;

use App\Enum\SequenceType;
use App\Service\DatabaseAuthorizationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FamilySizeController extends AbstractController
{
    /**
     * Estimates the total number of members across a given list of families.
     * This does not account for overlaps between families. 
     */
    #[Route('/api/calculate-family-size', name: 'api_calculate_family_size', methods: ['POST'])]
    public function calculateFamilySize(
        Connection $connection,
        DatabaseAuthorizationService $dbAuthService,
        LoggerInterface $logger,
        Request $request,
    ): JsonResponse
    {
        try {
            $familiesInput = $request->request->get('families', '');
            $unirefVersionInput = $request->request->get('unirefVersion', SequenceType::UniRef90->value);
            $fractionInput = $request->request->get('fraction', '1');
            $checkFamilySize = (bool)$request->request->get('fscheck', 1);
            $databaseIdValue = $request->request->get('dbVersion');

            $familiesInput = strtoupper($familiesInput);
            $familyList = array_filter(array_map('trim', preg_split('/[, ]+/', $familiesInput)));

            if (!is_array($familyList)) {
                return new JsonResponse(
                    ['error' => 'Invalid input. Please provide a JSON object with a "families" array.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (empty($familyList)) {
                return $this->prepareEmptyResponse();
            }

            if ($databaseIdValue !== null) {
                $databaseId = (int)$databaseIdValue;
            } else {
                $databaseId = null;
            }

            // Verify that the database exists and the user has permission to access it
            if ($databaseId === null || !$dbAuthService->isGranted($this->getUser(), $databaseId)) {
                return new JsonResponse(['error' => 'Invalid database format.'], Response::HTTP_BAD_REQUEST);
            }

            $userUnirefVersion = SequenceType::tryFrom($unirefVersionInput) ?? SequenceType::UniProt;

            $fraction = max(1, intval($fractionInput));

            // Fetch all required data in one go
            $familyInfo = $this->getFamilyInfo($connection, $familyList, $databaseId);

            // Calculate totals from the fetched data
            $totals = [
                'total_members' => array_sum(array_column($familyInfo, 'num_members')) / $fraction,
                'total_uniref90' => array_sum(array_column($familyInfo, 'num_uniref90_members')) / $fraction,
                'total_uniref50' => array_sum(array_column($familyInfo, 'num_uniref50_members')) / $fraction,
            ];

            // Determine if UniRef needs to be forced and which version
            list($forcedUnirefVersion, $sequenceLimitExceeded) = $this->determineForcedUniref($totals, $userUnirefVersion, $checkFamilySize);

            // Determine the final sequence type and computed total
            $sequenceType = $forcedUnirefVersion ?? $userUnirefVersion;

            $totalComputed = match ($sequenceType) {
                SequenceType::UniRef90 => $totals['total_uniref90'],
                SequenceType::UniRef50 => $totals['total_uniref50'],
                default => $totals['total_members'],
            };

            $responseData = $this->makeResponseData($totals, $familyInfo, $totalComputed, $forcedUnirefVersion, $sequenceLimitExceeded);
            return new JsonResponse($responseData);

        } catch (\JsonException $e) {
            $logger->warning('Failed to decode JSON body.', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Invalid JSON format.'], Response::HTTP_BAD_REQUEST);
        } catch (DbalException $e) {
            $logger->error('Database error calculating family size.', ['exception' => $e]);
            return new JsonResponse(['error' => 'A database error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            $logger->error('Unexpected error calculating family size.', ['exception' => $e]);
            return new JsonResponse(['error' => 'An internal server error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function prepareEmptyResponse(): JsonResponse
    {
        return new JsonResponse($this->makeResponseData([], [], 0, null, false));
    }

    /**
     * Determines which UniRef version to force, if any, based on size thresholds.
     */
    private function determineForcedUniref(array $totals, SequenceType $userUnirefVersion, bool $checkFamilySize): array
    {
        if (!$checkFamilySize) {
            return [null, false];
        }

        $maxUniprotSize = $this->getParameter('app.max_uniprot_seq_size');
        $maxUniref90Size = $this->getParameter('app.max_uniref90_seq_size');
        $maxUniref50Size = $this->getParameter('app.max_uniref50_seq_size');

        if ($totals['total_members'] > $maxUniprotSize) {
            if ($totals['total_uniref90'] > $maxUniref90Size) {
                // UniProt and UniRef90 exceed limits, force UniRef50
                $sequenceLimitExceeded = $totals['total_uniref50'] > $maxUniref50Size;
                $forceType = $userUnirefVersion === SequenceType::UniRef50 ? null : SequenceType::UniRef50;
                return [$forceType, $sequenceLimitExceeded];
            }
            // Only UniProt exceeds the limit, force UniRef90, unless the user specifies UniRef50
            $forceType = $userUnirefVersion === SequenceType::UniProt ? SequenceType::UniRef90 : null;
            return [$forceType, false];
        }

        // No limits exceeded
        return [null, false];
    }

    /**
     * Fetches all data for the given family IDs from the database.
     * @param {Connection} $connection - database connection object
     * @param {array} $familyIds - list of family IDs to query
     * @returns {array} array of associative arrays, each containing 'family', 'short_name',
     *      'num_members', 'num_uniref90_members', 'num_uniref50_members'
     */
    private function getFamilyInfo(Connection $connection, array $familyIds, ?int $databaseId): array
    {
        $sql = '
            SELECT
                family,
                database_id,
                short_name,
                num_members,
                num_uniref50_members,
                num_uniref90_members
            FROM family_info
            WHERE database_id = COALESCE(:db_id, (SELECT id FROM DatabaseMetadata ORDER BY id ASC LIMIT 1)) /* Default to the first database if no database is specified */
                AND family IN (:family_ids)
        ';

        $stmt = $connection->executeQuery(
            $sql,
            ['family_ids' => $familyIds, 'db_id' => $databaseId],
            ['family_ids' => Connection::PARAM_STR_ARRAY],
        );

        return $stmt->fetchAllAssociative();
    }

    /**
     * Formats the final API response data.
     */
    private function makeResponseData(array $totals, array $familyInfo, int $totalComputed, ?SequenceType $forcedUnirefVersion, bool $sequenceLimitExceeded): array
    {
        $familyData = array_map(function ($row) {
            return [
                'id' => $row['family'],
                'name' => $row['short_name'] ?? '',
                'count' => [
                    'uniprot' => (int) $row['num_members'],
                    'uniref90' => (int) $row['num_uniref90_members'],
                    'uniref50' => (int) $row['num_uniref50_members']
                ]
            ];
        }, $familyInfo);

        return [
            'totalFamilyCount' => count($familyData),
            'totalSequenceCount' => [
                'uniprot' => (int) ($totals['total_members'] ?? 0),
                'uniref50' => (int) ($totals['total_uniref50'] ?? 0),
                'uniref90' => (int) ($totals['total_uniref90'] ?? 0),
            ],
            'totalComputed' => $totalComputed,
            'familyData' => $familyData,
            'sequenceLimitExceeded' => $sequenceLimitExceeded,
            'forcedUnirefVersion' => $forcedUnirefVersion?->value,
            'forcedUnirefVersionText' => $forcedUnirefVersion?->getLabel(),
        ];
    }
}
