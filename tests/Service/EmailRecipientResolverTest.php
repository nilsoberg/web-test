<?php

namespace App\Tests\Service;

use App\Service\EmailRecipientResolverService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;

class EmailRecipientResolverTest extends TestCase
{
    public function testResolveFromFormReturnsNormalizedEmail(): void
    {
        $emailField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => ' USER@EXAMPLE.COM ',
        ]);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('has')->with('email')->willReturn(true);
        $form->expects($this->once())->method('get')->with('email')->willReturn($emailField);

        $resolver = new EmailRecipientResolverService();

        $this->assertSame('user@example.com', $resolver->resolveFromForm($form));
    }

    public function testResolveFromFormReturnsNullWhenEmailFieldIsMissing(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('has')->with('email')->willReturn(false);
        $form->expects($this->never())->method('get');

        $resolver = new EmailRecipientResolverService();

        $this->assertNull($resolver->resolveFromForm($form));
    }

    public function testResolveFromFormReturnsNullWhenEmailIsInvalid(): void
    {
        $emailField = $this->createConfiguredMock(FormInterface::class, [
            'getData' => 'not-an-email',
        ]);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('has')->with('email')->willReturn(true);
        $form->expects($this->once())->method('get')->with('email')->willReturn($emailField);

        $resolver = new EmailRecipientResolverService();

        $this->assertNull($resolver->resolveFromForm($form));
    }
}

