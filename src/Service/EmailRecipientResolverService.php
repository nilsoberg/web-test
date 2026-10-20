<?php

namespace App\Service;

use App\Entity\Job;
use Symfony\Component\Form\FormInterface;

class EmailRecipientResolverService
{
    public function resolveFromJob(Job $job): ?string
    {
        return $this->resolve($job->getUser()?->getEmail() ?? '');
    }

    public function resolveFromForm(FormInterface $form): ?string
    {
        if (!$form->has('email')) {
            return null;
        }
        $email = $form->get('email')->getData();
        return is_string($email) ? $this->resolve($email) : null;
    }

    private function resolve(string $email): ?string
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $normalizedEmail;
    }
}
