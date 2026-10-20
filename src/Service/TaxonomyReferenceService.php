<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TaxonomyReferenceService
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.taxonomy_reference_connection')]
        private readonly Connection $connection,
        #[Autowire('%app.taxonomy_reference_table%')]
        private readonly string $taxonomyTable
    )
    {
    }

    /**
     * Finds taxonomy values for the autocomplete endpoint
     * @param string $rank The rank to search in
     * @param string $query The partial string to search for
     * @param int $limit The maximum number of items to return (defaults to 10)
     * @return array[] Array, with each item being an array; i.e. [['rank_value' => 'Eukaryota'], ['rank_value' => 'Eukarya']]
     */
    public function searchAutocomplete(string $rank, string $query, int $limit = 10): array
    {
        $rank = mb_strtolower($rank);

        if (!in_array($rank, $this->getSearchableFields(), true)) {
            throw new \InvalidArgumentException(sprintf('Invalid rank "%s" specified in taxonomy search.', $rank));
        }

        $column = $this->connection->quoteIdentifier($rank);

        $sql = "
            SELECT DISTINCT {$column} AS rank_value
            FROM {$this->taxonomyTable}
            WHERE {$column} LIKE :query 
            ORDER BY {$column} ASC 
            LIMIT :limit
        ";

        $resultSet = $this->connection->executeQuery($sql, [
                'query' => $query . '%', // e.g., "Euk%"
                'limit' => $limit
            ], [
                // Explicitly bind the limit as an integer so PDO doesn't quote it
                'limit' => \PDO::PARAM_INT 
            ]);

        // Returns a fast, flat array: [['rank_value' => 'Eukaryota'], ['rank_value' => 'Eukarya']]
        return $resultSet->fetchAllAssociative();
    }

    /**
     * Helper to provide the list of searchable fields.
     */
    public function getSearchableFields(): array
    {
        return ['domain', 'kingdom', 'phylum', 'class', 'tax_order', 'family', 'genus', 'species'];
    }
}
