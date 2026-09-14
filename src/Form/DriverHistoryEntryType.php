<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Driver;
use App\Entity\DriverHistoryEntry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class DriverHistoryEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('driver', EntityType::class, [
                'class' => Driver::class,
                'choice_label' => 'fullName',
            ])
            ->add('text', TextareaType::class, [
                // required here (not on the entity): imported rows may have no text,
                // but an admin creating an entry must give a reason
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['rows' => 4],
            ])
            ->add('occurredAt', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('reportedBy', TextType::class, [
                'required' => false,
                'help' => 'Free-text name, only if the reporter has no account',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DriverHistoryEntry::class,
        ]);
    }
}
