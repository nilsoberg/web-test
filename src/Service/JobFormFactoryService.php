<?php

namespace App\Service;

use App\Auth\Roles;
use App\Dto\JobFormFactoryData;
use App\Dto\JobFormHandlerData;
use App\Enum\JobFormSubmissionHandlerType;
use App\Form\JobForm;
use App\Service\DatabaseListService;
use App\Service\SubmissionHandler\BasicSubmissionHandler;
use App\Service\SubmissionHandler\StringOrFileSubmissionHandler;
use App\Service\SubmissionHandler\StringToFileSubmissionHandler;
use App\Service\SubmissionHandler\SubmissionHandlerInterface;
use App\Service\SubmissionHandler\UploadedFileSubmissionHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles the creation of job submission forms.
 */
class JobFormFactoryService
{
    public function __construct(
        private readonly DatabaseListService $databaseService,
        private readonly FormFactoryInterface $formFactory,
        private readonly Security $security,
        private readonly StringOrFileSubmissionHandler $stringOrFileHandler,
        private readonly StringToFileSubmissionHandler $stringToFileHandler,
        private readonly UploadedFileSubmissionHandler $fileHandler,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    /**
     * Handler to create job form.
     * @param {JobFormFactoryData} $data - data to use to configure the form, and optionally
     *      prepopulate unmapped form fields with values
     * @param {JobFormSubmissionHandlerType} $handlerType - type of handler for uploaded files
     *      or data
     * @param $onCreated - function called on the $job object that is created in this method;
     *      allows data to be set (e.g. job name)
     * @returns JobFormHandlerData
     */
    public function createJobForm(
        JobFormFactoryData $data,
        JobFormSubmissionHandlerType $handlerType,
        ?callable $onCreated = null,
    ): JobFormHandlerData
    {
        $user = $this->security->getUser();
        $email = $user?->getEmail();

        $entityClass = $data->entityClass;
        $job = new $entityClass();

        if ($onCreated) {
            $onCreated($job);
        }

        $options = array_merge([
            'default_email_address' => $email,
        ], $data->extraOptions);

        if (is_subclass_of($data->formClass, JobForm::class)) {
            $options['lock_email_address'] = $email !== null;
        }

        if ($data->route) {
            $options['action'] = $this->urlGenerator->generate($data->route, ['f' => $data->formName]);
        }

        if (!isset($options['active_databases'])) {
            $options['active_databases'] = $this->databaseService->getActiveDatabases();
        }

        $form = $this->formFactory->createNamed($data->formName, $data->formClass, $job, $options);

        // Prepopulate form fields if there is data and the data maps to the form field
        if ($data->initialFormData) {
            foreach ($data->initialFormData as $field => $value) {
                if ($form->has($field)) {
                    $form->get($field)->setData($value);
                }
            }
        }

        $fileHandler = match($handlerType) {
            JobFormSubmissionHandlerType::StringOrFile  => $this->stringOrFileHandler,
            JobFormSubmissionHandlerType::StringToFile  => $this->stringToFileHandler,
            JobFormSubmissionHandlerType::Uploaded      => $this->fileHandler,
            default                                     => null,
        };

        return new JobFormHandlerData(
            formTypeName: $data->title,
            form: $form,
            job: $job,
            handler: $fileHandler,
        );
    }
}
