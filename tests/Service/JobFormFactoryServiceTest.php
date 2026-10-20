<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Service\DatabaseListService;
use App\Dto\JobFormFactoryData;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\ESTSSNFinalizationJob;
use App\Enum\JobFormSubmissionHandlerType;
use App\Form\ESTGenerateBlastForm;
use App\Form\ESTSSNFinalizationForm;
use App\Service\JobFormFactoryService;
use App\Service\SubmissionHandler\StringOrFileSubmissionHandler;
use App\Service\SubmissionHandler\StringToFileSubmissionHandler;
use App\Service\SubmissionHandler\UploadedFileSubmissionHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class JobFormFactoryServiceTest extends TestCase
{
    public function testCreateJobFormLocksEmailForLoggedInUser(): void
    {
        $dbRepo = $this->createMock(DatabaseListService::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $security = $this->createMock(Security::class);
        $stringOrFileHandler = $this->createMock(StringOrFileSubmissionHandler::class);
        $stringToFileHandler = $this->createMock(StringToFileSubmissionHandler::class);
        $uploadedFileHandler = $this->createMock(UploadedFileSubmissionHandler::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $service = new JobFormFactoryService(
            $dbRepo,
            $formFactory,
            $security,
            $stringOrFileHandler,
            $stringToFileHandler,
            $uploadedFileHandler,
            $urlGenerator
        );

        $user = new UserEntity();
        $user->setEmail('account@example.com');
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $urlGenerator->expects($this->never())
            ->method('generate');

        $form = $this->createMock(FormInterface::class);
        $formFactory->expects($this->once())
            ->method('createNamed')
            ->with(
                'estBlastForm',
                ESTGenerateBlastForm::class,
                $this->isInstanceOf(ESTGenerateBlastJob::class),
                $this->callback(function (array $options): bool {
                    return ($options['default_email_address'] ?? null) === 'account@example.com'
                        && ($options['lock_email_address'] ?? null) === true;
                })
            )
            ->willReturn($form);

        $data = new JobFormFactoryData(
            null,
            'estBlastForm',
            ESTGenerateBlastForm::class,
            ESTGenerateBlastJob::class,
            'BLAST'
        );

        $result = $service->createJobForm($data, JobFormSubmissionHandlerType::Basic);

        $this->assertSame($form, $result->form);
    }

    public function testCreateJobFormDoesNotLockEmailForGuestUser(): void
    {
        $dbRepo = $this->createMock(DatabaseListService::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $security = $this->createMock(Security::class);
        $stringOrFileHandler = $this->createMock(StringOrFileSubmissionHandler::class);
        $stringToFileHandler = $this->createMock(StringToFileSubmissionHandler::class);
        $uploadedFileHandler = $this->createMock(UploadedFileSubmissionHandler::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $service = new JobFormFactoryService(
            $dbRepo,
            $formFactory,
            $security,
            $stringOrFileHandler,
            $stringToFileHandler,
            $uploadedFileHandler,
            $urlGenerator
        );

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $urlGenerator->expects($this->never())
            ->method('generate');

        $form = $this->createMock(FormInterface::class);
        $formFactory->expects($this->once())
            ->method('createNamed')
            ->with(
                'estBlastForm',
                ESTGenerateBlastForm::class,
                $this->isInstanceOf(ESTGenerateBlastJob::class),
                $this->callback(function (array $options): bool {
                    return ($options['default_email_address'] ?? null) === null
                        && ($options['lock_email_address'] ?? null) === false;
                })
            )
            ->willReturn($form);

        $data = new JobFormFactoryData(
            null,
            'estBlastForm',
            ESTGenerateBlastForm::class,
            ESTGenerateBlastJob::class,
            'BLAST'
        );

        $result = $service->createJobForm($data, JobFormSubmissionHandlerType::Basic);

        $this->assertSame($form, $result->form);
    }

    public function testCreateJobFormDoesNotPassLockOptionForNonJobFormType(): void
    {
        $dbRepo = $this->createMock(DatabaseListService::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $security = $this->createMock(Security::class);
        $stringOrFileHandler = $this->createMock(StringOrFileSubmissionHandler::class);
        $stringToFileHandler = $this->createMock(StringToFileSubmissionHandler::class);
        $uploadedFileHandler = $this->createMock(UploadedFileSubmissionHandler::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $service = new JobFormFactoryService(
            $dbRepo,
            $formFactory,
            $security,
            $stringOrFileHandler,
            $stringToFileHandler,
            $uploadedFileHandler,
            $urlGenerator
        );

        $user = new UserEntity();
        $user->setEmail('account@example.com');
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $urlGenerator->expects($this->never())
            ->method('generate');

        $form = $this->createMock(FormInterface::class);
        $formFactory->expects($this->once())
            ->method('createNamed')
            ->with(
                'ssnFinalizationForm',
                ESTSSNFinalizationForm::class,
                $this->isInstanceOf(ESTSSNFinalizationJob::class),
                $this->callback(function (array $options): bool {
                    return ($options['default_email_address'] ?? null) === 'account@example.com'
                        && !array_key_exists('lock_email_address', $options);
                })
            )
            ->willReturn($form);

        $data = new JobFormFactoryData(
            null,
            'ssnFinalizationForm',
            ESTSSNFinalizationForm::class,
            ESTSSNFinalizationJob::class,
            'SSN Finalization'
        );

        $result = $service->createJobForm($data, JobFormSubmissionHandlerType::None);

        $this->assertSame($form, $result->form);
    }
}
