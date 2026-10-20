<?php

namespace App\Form;

use App\Entity\DatabaseMetadata;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;

class MetagenomeDatabaseForm extends AbstractType
{
    use Partial\DatabaseMetadataTrait;

    public function __construct(
        private readonly Security $security,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metagenomes', TextareaType::class, [
                'label' => 'Metagenome List (paste tab or space separated lines, with header)',
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'placeholder' => "id\tregion\tgender",
                ],
            ])
            ->add('metadata', TextareaType::class, [
                'label' => 'Metagenome Metadata (JSON format)',
                'required' => false,
                'attr' => [
                    'rows' => 12,
                    'placeholder' => "",
                ],
            ]);

        $builder->get('metagenomes')->addModelTransformer(new Transformer\MetagenomeTextTransformer());
        $builder->get('metadata')->addModelTransformer(new Transformer\MetagenomeMetadataTransformer());

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
