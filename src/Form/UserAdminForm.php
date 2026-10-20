<?php

namespace App\Form;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\Role;
use App\Auth\Entity\UserEntity;
use App\Auth\Repository\OrganizationRepository;
use App\Auth\Roles;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A form for handling user editing and creation.
 */
class UserAdminForm extends AbstractType
{
    public function __construct(
        private readonly Security $security,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class)
            ->add('email', EmailType::class)
            ->add('timezone', TimezoneType::class, [
                'input' => 'string',
                'label' => 'Timezone',
            ]);

        if ($options['is_edit']) {
            $builder->add('passwordFields', Type\PasswordFormType::class, [
                'mapped' => false,
                'label' => false,
                'require_password' => false,
            ]);
        }

        $builder->get('email')
            ->addModelTransformer(new Transformer\EmailToLowerCaseTransformer());

        // Check if the current user is a super admin
        if ($options['editor_super_admin']) {
			$builder
                ->add('primaryOrganization', EntityType::class, [
                    'class' => OrganizationEntity::class,
                    'choice_label' => 'name',
                    'placeholder' => 'None',
                    'query_builder' => function (OrganizationRepository $repo) use ($options) {
                        $qb = $repo->createQueryBuilder('o')->where('o.isProtected = :isProtected')->setParameter('isProtected', false);

                        /** @var \App\Auth\Entity\UserEntity $user */
                        $primaryOrg = $this->security->getUser()->getPrimaryOrganization();
                        if ($primaryOrg) {
                            $qb->andWhere('o.id = :orgId')->setParameter('orgId', $primaryOrg->getId());
                        }

                        return $qb->orderBy('o.name', 'ASC');
                    },
                ])
                ->add('devFeaturesEnabled', CheckboxType::class, [
                    'label' => 'Enable advanced features',
                    'required' => false,
                ]);
        }

        $builder
            ->add('globalRole', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'getDisplayName',
                'label' => 'Role in Organization',
                'query_builder' => function (EntityRepository $er) use ($options) {
                    // Order by entry in table (this typically is by order of authority, descending)
                    $qb = $er->createQueryBuilder('r')->orderBy('r.id', 'ASC');

                    // If the current editor is not a superadmin, then only show organization-specific
                    // roles, not global ones like superadmin
                    if (!$options['editor_super_admin']) {
                        $qb
                            ->where('r.name != :superAdminRole')
                            ->setParameter('superAdminRole', Roles::ROLE_SUPER_ADMIN);
                    }

                    return $qb;
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // editor_super_admin is a flag that indicates if the current user is a super admin
        $resolver->setDefaults([
            'attr' => [
                'novalidate' => 'novalidate',
                'spellcheck' => 'false',
            ],
            'data_class' => UserEntity::class,
            'editor_super_admin' => false,
            'is_edit' => false,
        ]);
    }
}
