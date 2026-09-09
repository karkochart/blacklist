<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('email', EmailType::class)
            ->add('name', TextType::class)
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'choices' => ['Admin' => 'ROLE_ADMIN'],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Every user is at least ROLE_USER.',
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false, // not a User property — hashed in the controller
                'required' => !$isEdit,
                'help' => $isEdit ? 'Leave blank to keep the current password.' : null,
                'constraints' => array_filter([
                    $isEdit ? null : new Assert\NotBlank(),
                    new Assert\Length(min: 8, max: 4096),
                ]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
