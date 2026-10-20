<?php

namespace App\Service;

use App\Dto\JobFormHandlerData;
use App\Enum\JobFormHandlerStatus;
use App\Service\JobFormHandlerService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

class JobFormSubmissionService
{
    private ?FlashBag $flashBag;

    public function __construct(
        private readonly JobFormHandlerService $formHandler,
        private readonly RequestStack $requestStack,
    )
    {
        $this->flashBag = $this->requestStack->getSession()->getFlashBag();
    }

    public function handleFormAndSetFlash(JobFormHandlerData $data, ?string $customFlashMessage = null): bool
    {
        // formHandler must be injected in the controller constructor
        $formStatus = $this->formHandler->handle($data);

        if ($formStatus === JobFormHandlerStatus::Success) {
            $flashMessage = $customFlashMessage ?? $data->formTypeName . ' job ID ' . $data->job->getId() . ' has been successfully submitted';
            $this->flashBag->add('success', $flashMessage);
            return true;
        }

        if ($formStatus === JobFormHandlerStatus::Error) {
            $errors = $this->getFormErrors($data->form);
            $this->flashBag->add('danger', [
                'title' => 'Unable to submit ' . $data->formTypeName . ' job.',
                'errors' => $errors
            ]);
        }

        return false;
    }

    /**
     * A helper method to get all form errors and format them.
     *
     * @param FormInterface $form The form to get errors from.
     * @return array An array of formatted error strings.
     */
    private function getFormErrors(FormInterface $form): array
    {
        $errors = [];

        // Get global errors
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        // Get errors from child fields
        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                $childErrors = $this->getFormErrors($childForm);
                if ($childErrors) {
                    $errors = array_merge($errors, $childErrors);
                }
            }
        }

        return $errors;
    }
}
