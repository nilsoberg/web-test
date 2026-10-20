<?php

namespace App\Auth\Controller;

use App\Auth\Roles;
use App\Entity\CGFPIdentifyJob;
use App\Entity\CGFPQuantifyJob;
use App\Entity\ESTClusterAnalysisJob;
use App\Entity\ESTColorSSNJob;
use App\Entity\ESTConvergenceRatioJob;
use App\Entity\ESTGenerateAccessionJob;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\ESTGenerateFamiliesJob;
use App\Entity\ESTGenerateFastaJob;
use App\Entity\ESTNeighborhoodConnectivityJob;
use App\Entity\ESTSSNFinalizationJob;
use App\Entity\GNTGNNJob;
use App\Entity\GNTDiagramBlastJob;
use App\Entity\GNTDiagramFastaJob;
use App\Entity\GNTDiagramSequenceIdJob;
use App\Entity\GNTViewDiagramJob;
use App\Entity\Job;
use App\Entity\TaxonomyAccessionJob;
use App\Entity\TaxonomyFamiliesJob;
use App\Entity\TaxonomyFastaJob;
use App\Repository\JobRepository;
use App\Service\LocalizedDateFormatterService;
use App\ViewModel\Factory\JobAdminViewModelFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin')]
#[IsGranted(Roles::ROLE_ORG_ADMIN)]
class JobController extends AbstractController
{
    // Map view groups to actual Doctrine single-table inheritance classes
    private const JOB_GROUPS = [
        'EST' => [
            ESTGenerateBlastJob::class,
            ESTGenerateFamiliesJob::class,
            ESTGenerateFastaJob::class,
            ESTGenerateAccessionJob::class,
            ESTColorSSNJob::class,
            ESTNeighborhoodConnectivityJob::class,
            ESTClusterAnalysisJob::class,
            ESTConvergenceRatioJob::class,
        ],
        'CGFP' => [
            CGFPIdentifyJob::class,
            CGFPQuantifyJob::class,
        ],
        'TAXONOMY' => [
            TaxonomyAccessionJob::class,
            TaxonomyFamiliesJob::class,
            TaxonomyFastaJob::class,
        ],
        'EST_FINALIZATION' => [
            ESTSSNFinalizationJob::class,
        ],
        'GNT' => [
            GNTGNNJob::class,
        ],
        'GND' => [
            GNTDiagramBlastJob::class,
            GNTDiagramFastaJob::class,
            GNTDiagramSequenceIdJob::class,
            GNTViewDiagramJob::class,
        ],
    ];

    public function __construct(
        private readonly LocalizedDateFormatterService $dateFormatter,
    ) {
    }

    #[Route('/jobs', name: 'app_admin_job_list')]
    public function index(
        Request $request,
        JobRepository $jobRepository,
        JobAdminViewModelFactory $jobAdminFactory,
        ValidatorInterface $validator,
    ): Response
    {
        $user = $this->getUser();

        // Month pagination
        $monthParam = $request->query->get('month');

        // Verify month pattern is correct
        $violations = $validator->validate($monthParam, [
            new Assert\NotBlank(),
            new Assert\Regex(['pattern' => '/^\d{4}-(0[1-9]|1[0-2])$/']),
        ]);
        if (count($violations) > 0) {
            $monthParam = date('Y-m');
        }

        $startOfMonth = new \DateTimeImmutable($monthParam . '-01 00:00:00');
        $endOfMonth = $startOfMonth->modify('first day of next month');

        // Selected job types
        $selectedGroups = $request->query->all('groups');
        $jobClasses = [];
        foreach ($selectedGroups as $group) {
            if (isset(self::JOB_GROUPS[$group])) {
                $jobClasses = array_merge($jobClasses, self::JOB_GROUPS[$group]);
            }
        }

        $jobs = $jobRepository->findJobsForAdminView(
            $user, 
            $startOfMonth, 
            $endOfMonth, 
            $jobClasses, 
            $this->isGranted(Roles::ROLE_SUPER_ADMIN),
            $this->isGranted(Roles::ROLE_ORG_ADMIN),
        );

        $jobViewModels = $jobAdminFactory->createJobViewModels($jobs);

        return $this->render('auth/job_stats.html.twig', [
            'jobs' => $jobViewModels,
            'currentMonth' => $startOfMonth,
            'selectedGroups' => $selectedGroups,
            'availableGroups' => array_keys(self::JOB_GROUPS)
        ]);
    }

    /**
     * Displays the details for a specific job, showing only the properties
     * that are unique to its subclass and traits.
     */
    #[Route('/job/{id}', name: 'admin_job_details')]
    public function show(Job $job): Response
    {
        $this->denyAccessUnlessGranted('JOB_VIEW', $job);

        $jobReflection = new \ReflectionClass($job);

        // Get properties from the base Job class to exclude them
        $baseJobReflection = new \ReflectionClass(Job::class);
        $baseProperties = $baseJobReflection->getProperties();
        $basePropertyNames = array_map(fn($prop) => $prop->getName(), $baseProperties);

        $allProperties = $jobReflection->getProperties();
        $specificProperties = [];
        $specificProperties['Job Name'] = $job->getJobName();
        $specificProperties['Status'] = $job->getFormattedStatus();
        $specificProperties['Database'] = $job->getDbVersion();

        foreach ($allProperties as $prop) {
            // We only want properties that are NOT from the base Job class.
            if (!in_array($prop->getName(), $basePropertyNames)) {
                $propName = $prop->getName();
                $value = null;

                // Prefer using a public getter method if it exists.
                $getter = 'get' . ucfirst($propName);
                $isser = 'is' . ucfirst($propName);

                if ($jobReflection->hasMethod($getter)) {
                    $value = $job->{$getter}();
                } elseif ($jobReflection->hasMethod($isser)) {
                    $value = $job->{$isser}();
                } else {
                    // Fallback to reflection for private/protected properties.
                    if (!$prop->isPublic()) {
                        $prop->setAccessible(true);
                    }
                    $value = $prop->getValue($job);
                }

                // Convert enums and other objects to a readable string format.
                if (is_object($value)) {
                    if ($value instanceof \UnitEnum) {
                        if (method_exists($value, 'getLabel')) {
                            $value = $value->getLabel();
                        } elseif ($value instanceof \BackedEnum) {
                            $value = ucfirst($value->value);
                        } else {
                            $value = $value->name;
                        }
                    } elseif ($value instanceof \DateTimeInterface) {
                        $value = $this->dateFormatter->format($value, skeleton: 'yMMMdjm');
                    }
                }

                // Create a human-readable label from the camelCase property name.
                $formattedName = ucwords(trim(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $propName))));
                $specificProperties[$formattedName] = $value;
            }
        }

        // Render the properties table into an HTML string.
        $html = $this->renderView('auth/job/partials/_modal_content.html.twig', [
            'properties' => $specificProperties,
        ]);

        return new JsonResponse([
            'html' => $html,
            'className' => $jobReflection->getShortName(),
            'jobId' => $job->getId(),
        ]);
    }
}
