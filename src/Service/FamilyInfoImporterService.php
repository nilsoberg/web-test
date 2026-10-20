<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class FamilyInfoImporterService
{
    public function __construct(
        private readonly Connection $connection,
    )
    {
    }

    /**
     * Creates the table and appropriate indexes if it does not yet exist.
     */
    public function createTableIfNotExists(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['family_info'])) {
            $sql = "
                CREATE TABLE family_info (
                    id INT AUTO_INCREMENT NOT NULL,
                    family VARCHAR(50) NOT NULL,
                    database_id INT NOT NULL,
                    num_members INT DEFAULT 0 NOT NULL,
                    num_uniref90_members INT DEFAULT 0 NOT NULL,
                    num_uniref50_members INT DEFAULT 0 NOT NULL,
                    short_name VARCHAR(50),
                    long_name VARCHAR(255),
                    INDEX idx_db_id (database_id),
                    INDEX idx_fam_id (family),
                    UNIQUE INDEX idx_db_family (database_id, family),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            ";

            $this->connection->executeStatement($sql);
        }

        if (!$schemaManager->tablesExist(['pfam_clan_mapping'])) {
            $sql = "
                CREATE TABLE pfam_clan_mapping (
                    id INT AUTO_INCREMENT NOT NULL,
                    database_id INT NOT NULL,
                    pfam_id VARCHAR(50) NOT NULL,
                    clan_id VARCHAR(50) NOT NULL,
                    INDEX idx_clan_db_id (database_id),
                    INDEX idx_pfam_id (pfam_id),
                    INDEX idx_clan_id (clan_id),
                    INDEX idx_db_clan (database_id, clan_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            ";

            $this->connection->executeStatement($sql);
        }
    }

    /**
     * Imports family size records from a TSV or CSV file into the database.
     * Expected file format (header optional):
     * family | uniprot_count | uniref90_count | uniref50_count | short_name | long_name
     */
    public function importFileForDatabase(int $databaseId, string $familyFilePath, string $pfamClanFilePath, bool $hasHeader = true): int
    {
        $this->createTableIfNotExists();

        $familyRecordHandler = function($data) use ($databaseId) {
            if (count($data) < 6) {
                return null; // Skip malformed rows
            }

            $shortName = substr($data[1], 0, 20);
            $longName = substr($data[2], 0, 255);

            return [
                'family' => trim($data[0]),
                'database_id' => $databaseId,
                'uniprot' => (int) $data[3],
                'uniref90' => (int) $data[5],
                'uniref50' => (int) $data[4],
                'short_name' => $shortName,
                'long_name' => $longName,
            ];
        };

        $insertFamilyBatch = function($records) {
            $rowPlaceholders = [];
            $parameters = [];

            foreach ($records as $i => $row) {
                $rowPlaceholders[] = "(:fam_{$i}, :db_{$i}, :up_{$i}, :u90_{$i}, :u50_{$i}, :sn_{$i}, :ln_{$i})";
                $parameters["fam_{$i}"] = $row['family'];
                $parameters["db_{$i}"]  = $row['database_id'];
                $parameters["up_{$i}"]  = $row['uniprot'];
                $parameters["u90_{$i}"] = $row['uniref90'];
                $parameters["u50_{$i}"] = $row['uniref50'];
                $parameters["sn_{$i}"] = $row['short_name'];
                $parameters["ln_{$i}"] = $row['long_name'];
            }

            $sql = sprintf(
                'INSERT INTO family_info (family, database_id, num_members, num_uniref90_members, num_uniref50_members, short_name, long_name) VALUES %s',
                implode(', ', $rowPlaceholders)
            );

            return ['sql' => $sql, 'parameters' => $parameters];
        };


        $pfamClanRecordHandler = function($data) use ($databaseId) {
            if (count($data) < 2) {
                return null; // Skip malformed rows
            }

            return [
                'clan_id' => $data[0],
                'pfam_id' => $data[1],
                'database_id' => $databaseId,
            ];
        };

        $insertPfamClanBatch = function($records) {
            $rowPlaceholders = [];
            $parameters = [];

            foreach ($records as $i => $row) {
                $rowPlaceholders[] = "(:db_{$i}, :pfam_{$i}, :clan_{$i})";
                $parameters["db_{$i}"]  = $row['database_id'];
                $parameters["pfam_{$i}"] = $row['pfam_id'];
                $parameters["clan_{$i}"] = $row['clan_id'];
            }

            $sql = sprintf(
                'INSERT INTO pfam_clan_mapping (database_id, clan_id, pfam_id) VALUES %s',
                implode(', ', $rowPlaceholders)
            );

            return ['sql' => $sql, 'parameters' => $parameters];
        };

        try {
            $this->connection->beginTransaction();

            $numFamilyImported = $this->importFile($familyFilePath, $familyRecordHandler, $insertFamilyBatch, $hasHeader);

            $numClanImported = $this->importFile($pfamClanFilePath, $pfamClanRecordHandler, $insertPfamClanBatch, $hasHeader);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $numFamilyImported + $numClanImported;
    }

    private function importFile(string $filePath, \Closure $recordHandler, \Closure $prepareBatchInsertHandler, bool $hasHeader): int
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException(sprintf('File "%s" does not exist or is not readable.', $filePath));
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Failed to open file "%s".', $filePath));
        }

        if ($hasHeader) {
            fgetcsv($handle, 0, "\t");
        }

        $batchSize = 1000;
        $records = [];
        $totalImported = 0;

        try {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                $record = $recordHandler($data);

                if ($record !== null) {
                    $records[] = $record;
                }

                if (count($records) >= $batchSize) {
                    $batchData = $prepareBatchInsertHandler($records);
                    $this->connection->executeStatement($batchData['sql'], $batchData['parameters']);
                    $totalImported += count($records);
                    $records = [];
                }
            }

            // Insert remaining records
            if (!empty($records)) {
                $batchData = $prepareBatchInsertHandler($records);
                $this->connection->executeStatement($batchData['sql'], $batchData['parameters']);
                $totalImported += count($records);
            }
        } catch (\Throwable $e) {
            fclose($handle);
            throw $e;
        }

        fclose($handle);
        return $totalImported;
    }

    /**
     * Deletes all family_info records associated with a given database ID.
     */
    public function deleteForDatabase(int $databaseId): int
    {
        $numFamilyDeleted = $this->connection->executeStatement(
            'DELETE FROM family_info WHERE database_id = :dbId',
            ['dbId' => $databaseId]
        );

        $numPfamClanDeleted = $this->connection->executeStatement(
            'DELETE FROM pfam_clan_mapping WHERE database_id = :dbId',
            ['dbId' => $databaseId]
        );

        return $numFamilyDeleted + $numPfamClanDeleted;
    }
}
