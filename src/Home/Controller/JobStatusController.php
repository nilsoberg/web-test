<?php

namespace App\Home\Controller;

use App\Attribute\RateLimit;
use App\Enum\JobStatus;
use App\Service\JobRetrieverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class JobStatusController extends AbstractController
{
    #[Route('/api/job-action/{id}/{key}/archive', name: 'app_job_action_archive', requirements: ['key' => Requirement::UUID_V6], methods: ['POST'])]
    #[RateLimit(limiter: 'auth_submission')]
    public function archive_job(
        int $id,
        string $key,
        EntityManagerInterface $entityManager,
        JobRetrieverService $jobRetrieverService,
    ): JsonResponse
    {
        $job = $jobRetrieverService->findJob($id, $key);
        if (!$job) {
            return new JsonResponse(
                ['error' => 'Invalid job.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($job->getUser() !== $this->getUser()) {
            return new JsonResponse(
                ['error' => 'Invalid argument.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $job->setStatus(JobStatus::Archived);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/api/job-action/{id}/{key}/cancel', name: 'app_job_action_cancel', requirements: ['key' => Requirement::UUID_V6], methods: ['POST'])]
    #[RateLimit(limiter: 'auth_submission')]
    public function cancel_job(
        int $id,
        string $key,
        EntityManagerInterface $entityManager,
        JobRetrieverService $jobRetrieverService,
    ): JsonResponse
    {
        $job = $jobRetrieverService->findJob($id, $key);
        if (!$job) {
            return new JsonResponse(
                ['error' => 'Invalid job.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($job->getUser() !== $this->getUser()) {
            return new JsonResponse(
                ['error' => 'Invalid argument.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $job->setStatus(JobStatus::Cancelling);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/api/job-status/{id}/{key}', name: 'app_job_status', requirements: ['key' => Requirement::UUID_V6], methods: ['POST'])]
    public function job_status(
        int $id,
        string $key,
        JobRetrieverService $jobRetrieverService,
    ): JsonResponse
    {
        $job = $jobRetrieverService->findJob($id, $key);
        if (!$job) {
            throw new NotFoundHttpException('Invalid Job.');
        }

        return new JsonResponse(['status' => $job->getStatus()?->value]);
    }
}
