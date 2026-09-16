<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Self-service editing of one's own account. Deliberately narrower than
 * UserType: no roles field — granting/revoking your own ROLE_ADMIN from your
 * own profile page is a footgun, not a feature. Role changes stay in
 * /admin/users, done by another admin.
 */
final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class)
            ->add('name', TextType::class)
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false, // not a User property — hashed in the controller
                'required' => false,
                'help' => 'Leave blank to keep the current password.',
                'constraints' => [new Assert\Length(min: 8, max: 4096)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
