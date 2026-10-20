<?php

namespace App\Command;

use App\Config\Constants;
use App\Config\Settings;
use App\Entity\CGFPIdentifyJob;
use App\Entity\CGFPQuantifyJob;
use App\Entity\ESTClusterAnalysisJob;
use App\Entity\ESTColorSSNJob;
use App\Entity\ESTConvergenceRatioJob;
use App\Entity\ESTGenerateAccessionJob;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\ESTGenerateFamiliesJob;
use App\Entity\ESTGenerateFastaJob;
use App\Entity\ESTGenerateJob;
use App\Entity\ESTNeighborhoodConnectivityJob;
use App\Entity\ESTSSNFinalizationJob;
use App\Entity\ExcludeFragmentsTrait;
use App\Entity\FilenameTrait;
use App\Entity\FilterByTaxonomyTrait;
use App\Entity\GNTDiagramBlastJob;
use App\Entity\GNTDiagramFastaJob;
use App\Entity\GNTDiagramSequenceIdJob;
use App\Entity\GNTGNNJob;
use App\Entity\Job;
use App\Entity\ProteinFamilyAdditionTrait;
use App\Entity\TaxonomyAccessionJob;
use App\Entity\TaxonomyFamiliesJob;
use App\Entity\TaxonomyFastaJob;
use App\Auth\Entity\UserEntity;
use App\Enum\DomainRegion;
use App\Enum\JobStatus;
use App\Enum\SequenceType;
use App\Enum\SummaryJobType;
use App\Repository\JobRepository;
use App\Service\ConfigurationManager;
use App\Service\JobStatisticsHelperService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[AsCommand(
    name: 'app:create-example-jobs',
    description: 'Creates example jobs for a specific user with a given status.',
)]
class CreateExampleJobsCommand extends Command
{
    const RESULTS_FULL = 'full';
    const RESULTS_NONE = 'none';
    const RESULTS_STATS = 'stats';

    private ?UserEntity $user = null;
    private ?JobStatus $status = null;
    private ?string $families = null;
    private string $resultsType = self::RESULTS_FULL;
    private array $jobSamples = [];
    private ?string $email = null;
    private ?string $resultsDir = null;

    private array $jobTypes = [SummaryJobType::EST->value, SummaryJobType::GNT->value, SummaryJobType::TAXONOMY->value, SummaryJobType::CGFP->value, SummaryJobType::GND->value, 'SSN', 'COLORSSN'];

    public function __construct(
        private ConfigurationManager $config,
        private EntityManagerInterface $entityManager,
        private JobRepository $jobRepository,
        private JobStatisticsHelperService $statsHelper,
        private ParameterBagInterface $parameterBag,
        private Filesystem $filesystem,
        private string $exampleJobsPath,
        private string $resultsSubdirectoryName,
    )
    {
        parent::__construct();
        $this->jobSamples = [];
    }

    protected function configure(): void
    {
        $jobTypes = implode("', '", $this->jobTypes);
        $this
            ->setDescription('Creates example job data for a specific user.')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'The email of the user to associate the jobs with.')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, "The status of the jobs (one of 'new', 'running', 'finished', 'failed', 'archived').")
            ->addOption('families', null, InputOption::VALUE_OPTIONAL, "If the --type flag is 'EST', this argument can be included to add a family to the computation (e.g. --families PF05544)")
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, "Job types to create (one of '$jobTypes')")
            ->addOption('results', null, InputOption::VALUE_OPTIONAL, "Use 'full' to copy full set of results and 'stats' to create directories and popluate directory entity")
            ->setHelp('This command allows you to insert example job data for a given user and persist them to the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->processInputParameters($input, $io)) {
            return Command::FAILURE;
        }

		$finder = new Finder();
        // Assume each sub-folder in the directory is a single Job.  If the job types are
        // EST, then the input directory will have a sub directory called 'ssn', and this is
        // created as a separate job and directory in the results directory.
        $finder->directories()->in($this->exampleJobsPath)->depth('== 0');

        foreach ($finder as $folder) {
            $path = $folder->getRealPath();

            $jobClass = $this->getJobClassName($path);

            if ($this->checkJobClass($jobClass)) {
                $job = $this->createJob($path);

                if ($job) {
                    $this->entityManager->persist($job);
                } else {
                    continue;
                }

                if (array_key_exists(ESTGenerateJob::class, class_parents($job))) {
                    if (in_array('SSN', $this->jobTypes)) {
                        $ssnJob = $this->createSSNJob($job, $path);
                        if ($ssnJob) {
                            $this->entityManager->persist($ssnJob);
                        }
                    }
                }
            }
        }

        $this->entityManager->flush();

        if ($this->status === JobStatus::Finished || $this->status === JobStatus::Running || $this->status === JobStatus::New) {
            if ($this->resultsType !== 'none') {
                $this->copyResults();
            }
        }

        $io->success(sprintf("Fake jobs with status '%s' have been created successfully for user %s.", $this->status->value, $this->email));

        return Command::SUCCESS;
    }

    private function checkJobClass(string $jobClass): bool
    {
        return (
            (
                str_contains($jobClass, 'ESTGenerate') &&
                in_array(SummaryJobType::EST, $this->jobTypes)
            ) ||
            (
                (
                 str_contains($jobClass, 'ColorSSN') ||
                 str_contains($jobClass, 'NeighborhoodConnectivity') ||
                 str_contains($jobClass, 'ConvergenceRatio') ||
                 str_contains($jobClass, 'ClusterAnalysis')
                ) &&
                in_array('COLORSSN', $this->jobTypes)
            ) ||
            (
                str_contains($jobClass, 'GNTGNN') &&
                in_array(SummaryJobType::GNT, $this->jobTypes)
            ) ||
            (
                str_contains($jobClass, 'GNTDiagram') &&
                in_array(SummaryJobType::GND, $this->jobTypes)
            ) ||
            (
                str_contains($jobClass, 'CGFP') &&
                in_array(SummaryJobType::CGFP, $this->jobTypes)
            ) ||
            (
                str_contains($jobClass, 'Taxonomy') &&
                in_array(SummaryJobType::TAXONOMY, $this->jobTypes)
            )
        );
    }

    private function processInputParameters(InputInterface $input, SymfonyStyle $io): bool
    {
        $this->email = mb_strtolower($input->getOption('email'));
        $this->families = $input->getOption('families');

        $statusString = $input->getOption('status');
        $this->status = JobStatus::tryFrom(strtolower($statusString));

        $requestedJobType = $input->getOption('type');
        if ($requestedJobType) {
            $jobType = SummaryJobType::tryFrom(strtoupper($requestedJobType));
            if ($jobType || ($requestedJobType === "SSN" || $requestedJobType === "COLORSSN")) {
                $this->jobTypes = [$jobType];
            }
        }

        $resultsType = $input->getOption('results');
        if (!$resultsType) {
            $this->resultsType = self::RESULTS_FULL;
        } else {
            $this->resultsType = $resultsType;
        }

        $this->resultsDir = $this->parameterBag->get('app.results_directory');

        if (!$this->status) {
            $io->error(sprintf("Invalid job status '%s'.", $statusString));
            $validStatuses = array_map(fn($case) => $case->value, JobStatus::cases());
            $io->note('Valid statuses are: ' . implode(', ', $validStatuses));
            return false;
        }

        $userRepository = $this->entityManager->getRepository(UserEntity::class);
        $this->user = $userRepository->findOneBy(['email' => $this->email]);

        if (!$this->user) {
            $io->error(sprintf("User with email '%s' not found.", $this->email));
            return false;
        }

        return true;
    }




    private function createJob(string $sampleDirPath): Job
    {
        $dirName = strtolower(pathinfo($sampleDirPath, \PATHINFO_BASENAME));
        $jobClass = $this->getJobClassName($sampleDirPath);

        $job = $this->createJobInstance($jobClass);

        $params = $this->loadJobJson("$sampleDirPath/input_params.json");

        $jobName = $this->getJobName($dirName);
        $this->populateCommonJobProperties($job, $jobName);

        $this->populateJobProperties($job, $params, $dirName);

        // Now, results
        $stats = $this->loadJobJson("$sampleDirPath/stats.json");
        $this->statsHelper->addResultsStats($job, $stats);

        $this->jobSamples[] = [$dirName, $job];

        $this->applyOverrides($job);

        return $job;
    }

    private function getJobClassName(string $sampleDirPath): ?string
    {
        $dirName = strtolower(pathinfo($sampleDirPath, \PATHINFO_BASENAME));
        if (str_contains($dirName, 'est_accession')) {
            return ESTGenerateAccessionJob::class;
        } else if (str_contains($dirName, 'est_blast')) {
            return ESTGenerateBlastJob::class;
        } else if (str_contains($dirName, 'est_family')) {
            return ESTGenerateFamiliesJob::class;
        } else if (str_contains($dirName, 'est_fasta')) {
            return ESTGenerateFastaJob::class;
        } else if (str_contains($dirName, 'convergence_ratio') || str_contains($dirName, 'conv_ratio')) {
            return ESTConvergenceRatioJob::class;
        } else if (str_contains($dirName, 'color_ssn')) {
            return ESTColorSSNJob::class;
        } else if (str_contains($dirName, 'cluster_analysis')) {
            return ESTClusterAnalysisJob::class;
        } else if (str_contains($dirName, 'neighborhood_conn')) {
            return ESTNeighborhoodConnectivityJob::class;
        } else if (str_contains($dirName, 'gnt')) {
            return GNTGNNJob::class;
        } else if (str_contains($dirName, 'gnd_accession')) {
            return GNTDiagramSequenceIdJob::class;
        } else if (str_contains($dirName, 'gnd_fasta')) {
            return GNTDiagramFastaJob::class;
        } else if (str_contains($dirName, 'gnd_blast')) {
            return GNTDiagramBlastJob::class;
        } else if (str_contains($dirName, 'taxonomy_accession')) {
            return TaxonomyAccessionJob::class;
        } else if (str_contains($dirName, 'taxonomy_family')) {
            return TaxonomyFamiliesJob::class;
        } else if (str_contains($dirName, 'taxonomy_fasta')) {
            return TaxonomyFastaJob::class;
        } else if (str_contains($dirName, 'cgfp_identify')) {
            return CGFPIdentifyJob::class;
        } else if (str_contains($dirName, 'cgfp_quantify')) {
            return CGFPQuantifyJob::class;
        } else {
            return null;
        }
    }

    private function getJobName(string $dirName): string
    {
        $jobName = substr($dirName, 7);
        $jobName = str_replace('est_', 'EST_', $jobName);
        $jobName = str_replace('gnt', 'GNT', $jobName);
        $jobName = str_replace('gnd', 'GND', $jobName);
        $jobName = str_replace('cgfp', 'CGFP', $jobName);
        $jobName = str_replace('fasta', 'FASTA', $jobName);
        $jobName = str_replace('blast', 'BLAST', $jobName);
        $jobName = str_replace('uniref', 'UniRef', $jobName);
        $jobName = str_replace('uniprot', 'UniProt', $jobName);
        $jobName = str_replace('ssn', 'SSN', $jobName);
        $parts = explode('_', $jobName);
        $nameParts = array_map(fn ($p) => ucfirst($p), $parts);
        $jobName = implode(' ', $nameParts);
        return $jobName;
    }

    private function populateJobProperties(Job $job, array $params, string $dirName)
    {
        $isGenerateJob = ($job->getSummaryJobType() === SummaryJobType::EST && array_key_exists(ESTGenerateJob::class, class_parents($job)));

        if ($this->classUsesTrait($job, ProteinFamilyAdditionTrait::class)) {
            $job->setFraction(1);
        }
        if ($this->classUsesTrait($job, ExcludeFragmentsTrait::class)) {
            $job->setExcludeFragments(false);
        }
        if ($this->classUsesTrait($job, FilterByTaxonomyTrait::class)) {
            $this->setTaxSearchParameters($job);
        }
        if ($this->classUsesTrait($job, FilenameTrait::class)) {
            $this->setUploadedFilename($job, $params['file_name'] ?? 'Input_file.txt');
        }

        if (get_class($job) === ESTClusterAnalysisJob::class) {
            $job->setMakeWebLogo($params['makeWebLogo'] ?? true);
            $job->setComputeConsensusResidues($params['computeConsensusResidues'] ?? true);
            $job->setConsensusResidues($params['consensusResidues'] ?? 'C');
            $job->setPidThresholds( ($params['pidThresholds'] && is_array($params['pidThresholds'])) ? $params['pidThresholds'] : [0.9,0.8,0.7,0.6,0.5,0.4,0.3,0.2,0.1] );
            $job->setMakeHmms($params['makeHmms'] ?? true);
            $job->setMakeLengthHistograms($params['makeLengthHistograms'] ?? true);
        }

        if (get_class($job) === ESTConvergenceRatioJob::class) {
            $job->setBlastEValue($params['alignmentScore']);
        }

        if ($isGenerateJob) {
            $this->populateESTGenerateJobProperties($job, $dirName);
        }
    }

    private function setUploadedFilename(Job $job, string $filename): void
    {
        $file = new UploadedFile(
            path: tempnam(sys_get_temp_dir(), 'test'),
            originalName: $filename,
            mimeType: 'text/plain',
            test: true
        );
        $job->setJobFile($file);
    }

    private function createSSNJob(Job $parentJob, string $sampleDirPath): ?Job
    {
        $ssnDirPath = "$sampleDirPath/ssn";
        if (!is_dir($ssnDirPath)) {
            return null;
        }

        $reflection = new \ReflectionClass(ESTSSNFinalizationJob::class);
        $job = $reflection->newInstanceWithoutConstructor();

        $params = $this->loadJobJson("$ssnDirPath/input_params.json");

        $this->populateCommonJobProperties($job, 'EST SSN Finalization Job');
        $job->setComputeNeighborhoodConnectivity(false);
        $job->setThresholdValue($params['ascore'] ?? 23);
        $job->setExcludeFragments(false);
        $job->setMinLength(Constants::DEFAULT_MIN_LENGTH);
        $job->setMaxLength(Constants::DEFAULT_MAX_LENGTH);
        $this->setTaxSearchParameters($job);

        $stats = $this->loadJobJson("$ssnDirPath/stats.json");
        $job->setResultFileStats(json_encode($stats['file_stats'] ?? []));

        $this->applyOverrides($job);

        $parentJob->addChildJob($job);

        $dirName = strtolower(pathinfo($sampleDirPath, \PATHINFO_BASENAME));
        $this->jobSamples[] = ["$dirName/ssn", $job];

        return $job;
    }

    private function createJobInstance(string $jobClass): Job
    {
        $reflection = new \ReflectionClass($jobClass);
        /* $job is an instance of the $className class */
        $job = $reflection->newInstanceWithoutConstructor();

        $parentReflection = new \ReflectionClass(Job::class); 

        // Set the childJobs property to a default array
        if ($parentReflection->hasProperty('childJobs')) {
            $childJobsProperty = $parentReflection->getProperty('childJobs');
            $childJobsProperty->setAccessible(true);
            $childJobsProperty->setValue($job, new ArrayCollection());
        }

        return $job;
    }

    private function loadJobJson(string $path): array
    {
        if (file_exists($path)) {
            $str = file_get_contents($path);
            return json_decode($str, true);
        } else {
            return [];
        }
    }

    private function isJobUniref(string $dirName): bool
    {
        return str_contains($dirName, 'uniref');
    }

    private function isJobDomain(string $dirName): bool
    {
        return str_contains($dirName, 'domain');
    }

    private function shouldAddFamilyToJob(string $dirName): bool
    {
        return str_contains($dirName, 'family');
    }

    /**
     * Applies status-specific and other required overrides for testing.
     * This is called AFTER a job has been populated with its default data.
     */
    private function applyOverrides(Job $job): void
    {
        $job->setStatus($this->status);

        $now = new \DateTimeImmutable();
        switch ($this->status) {
            case JobStatus::New:
                $job->setTimeCreated($now);
                $job->setTimeStarted(null);
                $job->setTimeCompleted(null);
                break;
            case JobStatus::Running:
                $job->setTimeCreated($now->modify('-5 minutes'));
                $job->setTimeStarted($now);
                $job->setTimeCompleted(null);
                break;
            case JobStatus::Finished:
                $timeCreated = $now->modify('-5 hours');
                $job->setTimeCreated($timeCreated);
                $job->setTimeStarted($timeCreated->modify('+5 minutes'));
                $job->setTimeCompleted($now);
                break;
            case JobStatus::Failed:
                $job->setTimeCreated($now->modify('-5 minutes'));
                $job->setTimeStarted($now);
                $job->setTimeCompleted($now);
                break;
            case JobStatus::Archived:
                $timeCreated = $now->modify('-5 hours');
                $job->setTimeCreated($timeCreated);
                $job->setTimeStarted($timeCreated->modify('+5 minutes'));
                $job->setTimeCompleted($now);
                break;
        }
    }

    private function populateESTGenerateJobProperties(ESTGenerateJob $job, string $dirName): void
    {
        if ($this->shouldAddFamilyToJob($dirName)) {
            $job->setAddFamilies($this->families);
        }

        $isUniref = $this->isJobUniref($dirName);
        if ($isUniref) {
            $job->setUnirefVersion(SequenceType::UniRef90);
        }

        $isDomain = $this->isJobDomain($dirName);
        if ($isDomain) {
            $job->setDomain(true);
            $job->setDomainRegion(DomainRegion::Domain);
        }

        $job->setAllByAllBlastEValue(Constants::DEFAULT_ALL_BY_ALL_BLAST_EVALUE);
    }

    /**
     * Populates the absolute base properties of a job that are set by the constructor.
     */
    private function populateCommonJobProperties(Job $job, string $jobName): void
    {
        $job->setUser($this->user);
        $job->setUuid(\Symfony\Component\Uid\Uuid::v6());
        $job->setJobName($jobName);

        // Default properties that get overridden by applyOverrides
        $job->setStatus(JobStatus::New);
        $job->setTimeCreated(new \DateTimeImmutable());

        // Properties with fixed values for faking
        $job->setDbVersion($this->config->get(Settings::DEFAULT_DATABASE_VERSION));
        $job->setIsPublic(false);
        $job->setIsExample(false);
        $job->setSchedulerJobId(-1);
    }

    private function setTaxSearchParameters(Job $job)
    {
        $job->setTaxSearch(['predefined' => 'bacteria_fungi', 'conditions' => []]);
    }




    private function copyResults(): void
    {
        foreach ($this->jobSamples as $job)
        {
            $sampleDir = $this->exampleJobsPath . '/' . $job[0];
            $this->setupJobDirectory($job[1], $sampleDir);
        }
    }

	private function setupJobDirectory(Job $job, string $sourceDatasetPath): void
    {
        // Check if the source dataset actually exists
        if (!$this->filesystem->exists($sourceDatasetPath)) {
            throw new \InvalidArgumentException("Source dataset path does not exist: {$sourceDatasetPath}");
        }

        $jobDirectoryPath = $this->resultsDir . '/' . $job->getId() . '/' . $this->resultsSubdirectoryName;

        try {
            $this->filesystem->mkdir($jobDirectoryPath);
            $this->mirrorJobDirectory($sourceDatasetPath, $jobDirectoryPath);
        } catch (IOExceptionInterface $exception) {
            throw new HttpException(500, "An error occurred for job {$job->getId()}: " . $exception->getMessage());
        }
    }

    private function mirrorJobDirectory(string $sourceDatasetPath, string $jobDirectoryPath): void
    {
        $finder = new Finder();
        // If the source is a directory, mirror it. If it's a file, copy it.
        if (is_dir($sourceDatasetPath)) {
            if ($this->resultsType === self::RESULTS_FULL) {
                $this->filesystem->mirror($sourceDatasetPath, $jobDirectoryPath);
            } else if ($this->resultsType === self::RESULTS_STATS) {
                foreach ($finder->in($sourceDatasetPath) as $file) {
                    $filename = $file->getFilenameWithoutExtension();
                    if ($filename === 'stats' || $filename === 'acc_counts') {
                        $targetFilename = 'stats.json';
                        $this->filesystem->copy($file->getRealPath(), $jobDirectoryPath . '/' .  $targetFilename);
                    }
                }
            }
        } else {
            // To keep the original filename, we get it from the path
            $filename = basename($sourceDatasetPath);
            $this->filesystem->copy($sourceDatasetPath, $jobDirectoryPath . '/' . $filename);
        }
    }

    private function classUsesTrait(Job $job, string $traitName): bool
    {
        $traits = class_uses($job);
        return array_key_exists($traitName, $traits);
    }
}
