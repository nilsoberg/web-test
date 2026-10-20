<?php

namespace App\Form\Type;

use App\Entity\Job;
use App\Service\ParentTransferService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

class ParentJobReferenceType extends AbstractType
{
    public const DEFAULT_SSN_FILE_PLACEHOLDER = 'Select a XGMML or zip file to upload';

    public function __construct(
        private readonly ParentTransferService $transferService,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('parent_id', HiddenType::class, [
                'required' => $options['parent_required'] ?? false,
                'data' => $options['parent_id'] ?? '',
                'constraints' => [
                    new Assert\Regex(pattern: '/^\d+$/', message: 'The parent ID is not valid.'),
                ],
            ])
            ->add('parent_key', HiddenType::class, [
                'required' => $options['parent_required'] ?? false,
                'data' => $options['parent_key'] ?? '',
                'constraints' => [
                    new Assert\Uuid(versions: [6], message: 'The ID is not valid (format).'),
                ],
            ]);

        if ($options['parent_subtype_required']) {
            $builder
                ->add('parent_subtype', HiddenType::class, [
                    'required' => true,
                    'data' => $options['parent_subtype'] ?? '',
                    'constraints' => [
                        new Assert\Regex(pattern: '/^[A-Za-z0-9_-]+$/', message: 'The parent subtype is not valid.'),
                    ],
                ]);
        }

        // We listen to SUBMIT on the sub-form, but we must modify the parent entity
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options) {
            $form = $event->getForm();

            // In a sub-form, the parent data holds the main Job object
            $job = $form->getParent()?->getData();

            if (!$job instanceof Job) {
                return;
            }

            $parentId = $form->get('parent_id')->getData();
            $parentUuid = $form->get('parent_key')->getData();

            if ($parentId && $parentUuid) {
                $transferId = $form->has('parent_subtype') ? $form->get('parent_subtype')->getData() : null;
                $result = $this->transferService->transferJob(
                    job: $job,
                    parentId: $parentId,
                    parentUuid: $parentUuid,
                    subtypeRequired: $options['parent_subtype_required'] ?? false,
                    subtype: $transferId,
                );

                if (!$result) {
                    $form->get('parent_id')->addError(new FormError('Invalid source job.'));
                }
            } else if ($options['parent_required']) {
                $form->get('parent_id')->addError(new FormError('Invalid source job (2).'));
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['parent_metadata'] = $options['parent_metadata'];
        $view->vars['parent_id'] = $options['parent_id'];
        $view->vars['parent_key'] = $options['parent_key'];
        $view->vars['parent_subtype'] = $options['parent_subtype'];
        $view->vars['suggested_name'] = $options['suggested_name'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'parent_required' => true,
            'parent_id' => null,
            'parent_key' => null,
            'parent_subtype_required' => true,
            'parent_subtype' => null,
            'parent_metadata' => null,
            'parent_subtype_name' => null,
            'suggested_name' => null,
        ]);

        $resolver->setAllowedTypes('parent_id', ['null', 'int']);
        $resolver->setAllowedTypes('parent_key', ['null', 'string', \Symfony\Component\Uid\Uuid::class]);
    }
}
