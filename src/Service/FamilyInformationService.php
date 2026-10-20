<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Psr\Log\LoggerInterface;

class FamilyInformationService
{
    private const CLAN_BASE_URL = 'https://www.ebi.ac.uk/interpro/set/pfam/';
    private const PFAM_BASE_URL = 'https://www.ebi.ac.uk/interpro/entry/pfam/';
    private const INTERPRO_BASE_URL = 'https://www.ebi.ac.uk/interpro/entry/';

    private array $prefixMap = [
        'CLPF' => self::CLAN_BASE_URL, 
        'CL'   => self::CLAN_BASE_URL, 
        'PF'   => self::PFAM_BASE_URL,
        'IPR'  => self::INTERPRO_BASE_URL,
    ];


    public function __construct(
        private readonly Connection $connection,
    )
    {
    }

    /**
     * Get a list of families based on the search criteria.
     * @param {string} $search - family ID or name to search for (if empty, shows all)
     * @param {string} $filter - family type to restrict search to (if empty, uses all types)
     * @param {bool} $showAll - show all families matching the given criteria
     * @param {int} $databaseId - the ID of the database to use for getting the list of available
     *     families and stats; this should already be validated by the consumer
     * @return {array} list of families and size data
     */
    public function getFamilyList(string $search, string $filter, bool $showAll, int $databaseId) : array
    {
        $whereConditions = ['database_id = :db_id'];
        $params = ['db_id' => $databaseId];

        if (!empty($search)) {
            $whereConditions[] = '(family LIKE :search OR short_name LIKE :search OR long_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        // Filter by family type
        if (in_array($filter, ['CL', 'PF', 'IPR'])) {
            $whereConditions[] = 'family LIKE :filter';
            $params['filter'] = $filter . '%';
        }

        $whereSql = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Perform query 
        $limit = $showAll === true ? '' : 'LIMIT 100';
        $dataSql = "SELECT * FROM family_info $whereSql $limit";
        $families = $this->connection->fetchAllAssociative($dataSql, $params);

        foreach ($families as &$row) {
            $row['url'] = null;
            $id = $row['family'];
            $url = $this->makeUrl($id);
            if ($url) {
                $row['url'] = $url;
            }
        }

        return $families;
    }

    /**
     * Get a mapping of Pfam Clans to PFAM.
     * @param {string} $search - clan ID or name to search for (if empty, shows all)
     * @param {bool} $showAll - show all clans matching the given criteria
     * @param {int} $databaseId - the ID of the database to use for getting the list of available
     *     families and stats; this should already be validated by the consumer
     * @return {array} clan mapping showing list of Pfams and sizes
     */
    public function getPfamClanMapping(string $search, bool $showAll, int $databaseId) : array
    {
        $params = ['db_id' => $databaseId];
        $whereConditions = [
            "c.clan_id != ''",
            'c.database_id = :db_id',
        ];

        if (!empty($search)) {
            $whereConditions[] = '(cl_info.family LIKE :search OR cl_info.short_name LIKE :search OR cl_info.long_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Perform query 
        $limit = $showAll === true ? '' : 'LIMIT 100';

        $dataSql = "SELECT 
                        c.clan_id, 
                        cl_info.short_name AS clan_name,
                        cl_info.num_members AS clan_size,
                        cl_info.num_uniref90_members AS clan_u90,
                        cl_info.num_uniref50_members AS clan_u50,
                        c.pfam_id,
                        pf_info.short_name AS pfam_name,
                        pf_info.num_members AS pfam_size,
                        pf_info.num_uniref90_members AS pfam_u90,
                        pf_info.num_uniref50_members AS pfam_u50
                    FROM pfam_clan_mapping c
                    LEFT JOIN family_info cl_info ON c.clan_id = cl_info.family AND cl_info.database_id = :db_id
                    LEFT JOIN family_info pf_info ON c.pfam_id = pf_info.family AND pf_info.database_id = :db_id
                    $whereSql
                    ORDER BY c.clan_id ASC, c.pfam_id ASC
                    $limit";
        $results = $this->connection->fetchAllAssociative($dataSql, $params);

        $clans = [];
        foreach ($results as $row) {
            $cid = $row['clan_id'] ?? 'Unclassified';

            // Create a clan entry
            if (!isset($clans[$cid])) {
                $clans[$cid] = [
                    'id' => $cid,
                    'name' => $row['clan_name'],
                    'size' => $row['clan_size'],
                    'u90' => $row['clan_u90'],
                    'u50' => $row['clan_u50'],
                    'url' => $this->makeUrl($cid),
                    'members' => []
                ];
            }

            // Add a new family to the clan
            $clans[$cid]['members'][] = [
                'id' => $row['pfam_id'],
                'name' => $row['pfam_name'],
                'size' => $row['pfam_size'],
                'u90' => $row['pfam_u90'],
                'u50' => $row['pfam_u50'],
                'url' => $this->makeUrl($row['pfam_id']),
            ];
        }

        return $clans;
    }

    /**
     * Make a URL to link to for more family information.
     * @param {string} $id - family ID (e.g. PFXXXXX)
     * @return {string} $url (null if no URL was able to be formed)
     */
    private function makeUrl(string $id): ?string
    {
        $url = null;
        foreach ($this->prefixMap as $prefix => $baseUrl) {
            if (str_starts_with($id, $prefix)) {
                $url = $baseUrl . $id;
                break;
            }
        }
        return $url;
    }
}
