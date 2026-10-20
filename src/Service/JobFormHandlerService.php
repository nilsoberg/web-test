<?php

namespace App\Service;

use App\Dto\JobFormHandlerData;
use App\Enum\JobFormHandlerStatus;
use App\Service\SubmissionHandler\BasicSubmissionHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles the processing of a job submission form.
 * This service encapsulates the logic for handling the request,
 * validating the form, and creating a job.
 */
class JobFormHandlerService
{
    public function __construct(
        private BasicSubmissionHandler $basicHandler,
        private RequestStack $requestStack,
        private Security $security,
    )
    {
    }

    public function handle(JobFormHandlerData $data): JobFormHandlerStatus
    {
        $request = $this->requestStack->getCurrentRequest();

        $data->form->handleRequest($request);

        if (!$data->form->isSubmitted()) {
            return JobFormHandlerStatus::NotSubmitted;
        }

        if ($this->isSubmittedEmailDifferentFromLoggedInUser($data->form)) {
            return JobFormHandlerStatus::Error;
        }

        if ($data->form->isValid()) {
            if ($data->handler) {
                $data->handler->handle($data->form, $data->job);
            } else {
                $this->basicHandler->handle($data->form, $data->job);
            }

            return JobFormHandlerStatus::Success;
        }

        return JobFormHandlerStatus::Error;
    }

    private function isSubmittedEmailDifferentFromLoggedInUser(FormInterface $form): bool
    {
        if (!$form->has('email')) {
            return false;
        }

        $currentUser = $this->security->getUser();
        if ($currentUser === null || !method_exists($currentUser, 'getEmail')) {
            return false;
        }

        $accountEmail = mb_strtolower(trim((string) $currentUser->getEmail()));
        $submittedEmail = mb_strtolower(trim((string) $form->get('email')->getData()));

        if ($accountEmail === '' || $submittedEmail === '' || $accountEmail === $submittedEmail) {
            return false;
        }

        $form->get('email')->addError(new FormError('Email must match your account email.'));
        return true;
    }
}
