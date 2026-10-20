<?php

namespace App\Form;

use App\Entity\DatabaseMetadata;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;

class DatabaseMetadataForm extends AbstractType
{
    use Partial\DatabaseMetadataTrait;

    public function __construct(
        private readonly Security $security,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addDatabaseMetadataFields($builder, $options, $this->security);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DatabaseMetadata::class,
            'constraints' => [
                new Callback([$this, 'validateDatabaseMetadataFormFields']) // validateDatabaseMetadataFormFields comes from DatabaseMetadataTrait
            ],
        ]);
    }
}
