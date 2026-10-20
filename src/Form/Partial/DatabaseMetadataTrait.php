<?php

namespace App\Form\Partial;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Repository\OrganizationRepository;
use App\Auth\Roles;
use App\Entity\DatabaseMetadata;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

trait DatabaseMetadataTrait
{
    public function addDatabaseMetadataFields(FormBuilderInterface $builder, array $options, Security $security): void
    {
        $builder
            ->add('publicName', TextType::class, [
                'label' => 'Public Name',
                'attr' => ['placeholder' => 'e.g. HMP1']
            ])
            ->add('dbName', TextType::class, [
                'label' => 'Internal Database Name',
                'help' => 'Must match the key in backend config file. Only letters, numbers, and underscores allowed.',
                'attr' => ['placeholder' => 'e.g. hmp_db'],
                'constraints' => [
                    new Assert\NotBlank(message: 'The internal database name cannot be blank.'),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z0-9_]+$/',
                        message: 'The internal database name can only contain letters, numbers, and underscores.'
                    )
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Make Active',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description/Arbitrary Data',
                'required' => false,
                'attr' => ['rows' => 5]
            ]);


        if ($this->security->isGranted(Roles::ROLE_SUPER_ADMIN)) {
            $builder
                ->add('isPublic', CheckboxType::class, [
                    'label' => 'Make Public (all users)',
                    'required' => false,
                    'attr' => ['class' => 'form-check-input'],
                ])
                ->add('organization', EntityType::class, [
                    'class' => OrganizationEntity::class,
                    'choice_label' => 'name',
                    'label' => 'Assign to Organization',
                    'placeholder' => 'Select an organization',
                    'required' => false,
                    'query_builder' => function (OrganizationRepository $repo) {
                        $qb = $repo->createQueryBuilder('o')->where('o.isProtected = :isProtected')->setParameter('isProtected', false);
                        return $qb->orderBy('o.name', 'ASC');
                    },
                ]);
        }
    }

    public function validateDatabaseMetadataFormFields(DatabaseMetadata $database, ExecutionContextInterface $context): void
    {
        if ($database->isPublic() && $database->getOrganization() !== null) {
            // Attach the error bubble directly to the user-facing form elements
            $context->buildViolation('A public database cannot belong to an organization.')
                ->atPath('organization') // Insert the error message right under the select box in Twig
                ->addViolation();
                
            $context->buildViolation('Please uncheck this or clear the organization field.')
                ->atPath('isPublic') // Insert the error right under the checkbox
                ->addViolation();
        }
    }
}
