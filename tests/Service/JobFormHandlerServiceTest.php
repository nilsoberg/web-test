<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Dto\JobFormHandlerData;
use App\Entity\ESTGenerateBlastJob;
use App\Enum\JobFormHandlerStatus;
use App\Service\JobFormHandlerService;
use App\Service\SubmissionHandler\BasicSubmissionHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class JobFormHandlerServiceTest extends TestCase
{
    public function testHandleRejectsSubmissionWhenEmailDiffersFromLoggedInUser(): void
    {
        $basicHandler = $this->createMock(BasicSubmissionHandler::class);
        $security = $this->createMock(Security::class);
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $service = new JobFormHandlerService($basicHandler, $requestStack, $security);

        $user = new UserEntity();
        $user->setEmail('account@example.com');
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $emailField = $this->createMock(FormInterface::class);
        $emailField->expects($this->once())
            ->method('getData')
            ->willReturn('other@example.com');
        $emailField->expects($this->once())
            ->method('addError')
            ->with($this->isInstanceOf(FormError::class));

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('handleRequest')
            ->with($this->isInstanceOf(Request::class));
        $form->expects($this->once())
            ->method('isSubmitted')
            ->willReturn(true);
        $form->expects($this->once())
            ->method('has')
            ->with('email')
            ->willReturn(true);
        $form->expects($this->exactly(2))
            ->method('get')
            ->with('email')
            ->willReturn($emailField);
        $form->expects($this->never())
            ->method('isValid');

        $job = new ESTGenerateBlastJob();
        $data = new JobFormHandlerData('BLAST', $form, $job);

        $basicHandler->expects($this->never())
            ->method('handle');

        $status = $service->handle($data);

        $this->assertSame(JobFormHandlerStatus::Error, $status);
    }

    public function testHandleAllowsSubmissionWhenEmailMatchesLoggedInUser(): void
    {
        $basicHandler = $this->createMock(BasicSubmissionHandler::class);
        $security = $this->createMock(Security::class);
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $service = new JobFormHandlerService($basicHandler, $requestStack, $security);

        $user = new UserEntity();
        $user->setEmail('account@example.com');
        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $emailField = $this->createMock(FormInterface::class);
        $emailField->expects($this->once())
            ->method('getData')
            ->willReturn('account@example.com');
        $emailField->expects($this->never())
            ->method('addError');

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('handleRequest')
            ->with($this->isInstanceOf(Request::class));
        $form->expects($this->once())
            ->method('isSubmitted')
            ->willReturn(true);
        $form->expects($this->once())
            ->method('has')
            ->with('email')
            ->willReturn(true);
        $form->expects($this->once())
            ->method('get')
            ->with('email')
            ->willReturn($emailField);
        $form->expects($this->once())
            ->method('isValid')
            ->willReturn(true);

        $job = new ESTGenerateBlastJob();
        $data = new JobFormHandlerData('BLAST', $form, $job);

        $basicHandler->expects($this->once())
            ->method('handle')
            ->with($form, $job);

        $status = $service->handle($data);

        $this->assertSame(JobFormHandlerStatus::Success, $status);
    }
}

