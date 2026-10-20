<?php

namespace App\Command;

use App\Entity\DatabaseMetadata;
use App\Entity\SequenceDatabaseMetadata;
use App\Service\FamilyInfoImporterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:family-info',
    description: 'Imports or purges family size records for a given database ID.'
)]
class ManageFamilyInfoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilyInfoImporterService $importer,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('db-id', InputArgument::REQUIRED, 'The database ID')
            ->addOption('import-family-file', 'f', InputOption::VALUE_OPTIONAL, 'Path to the TSV/CSV file containing the family mapping to import')
            ->addOption('import-clan-file', 'c', InputOption::VALUE_OPTIONAL, 'Path to the TSV/CSV file containing the Pfam-Clan mapping to import')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete all family sizes for the given database ID')
            ->addOption('no-header', null, InputOption::VALUE_NONE, 'Set if the import file lacks a header row');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dbId = (int) $input->getArgument('db-id');
        $importFamilyFile = $input->getOption('import-family-file');
        $importClanFile = $input->getOption('import-clan-file');
        $shouldDelete = $input->getOption('delete');

        // Verify that the database ID exists
        $dbMetadata = $this->em->getRepository(DatabaseMetadata::class)->find($dbId);
        if (!$dbMetadata) {
            $io->error(sprintf('Database with ID %d was not found.', $dbId));
            return Command::FAILURE;
        }

        if ((!$importFamilyFile || !$importClanFile) && !$shouldDelete) {
            $io->error('You must specify either --import-file=<path> or --delete.');
            return Command::INVALID;
        }

        // Handle deletion
        if ($shouldDelete) {
            $confirm = $io->confirm(
                sprintf('Are you sure you want to delete all family size entries for database ID %d (%s)?', $dbId, $dbMetadata->getDbName()),
                false
            );

            if ($confirm) {
                $deletedCount = $this->importer->deleteForDatabase($dbId);
                $io->success(sprintf('Deleted %d family size records for database ID %d.', $deletedCount, $dbId));
            } else {
                $io->note('Deletion cancelled.');
            }
        }

        // Handle import
        if ($importFamilyFile && $importClanFile) {
            $hasHeader = !$input->getOption('no-header');
            
            $io->section(sprintf('Importing family sizes for database ID %d (%s)', $dbId, $dbMetadata->getDbName()));
            
            try {
                $importedCount = $this->importer->importFileForDatabase($dbId, $importFamilyFile, $importClanFile, $hasHeader);
                $io->success(sprintf('Successfully imported %d records from "%s" and "%s".', $importedCount, $importFamilyFile, $importClanFile));
            } catch (\Exception $e) {
                $io->error('Import failed: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
