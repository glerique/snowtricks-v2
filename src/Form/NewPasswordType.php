<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A "password + confirmation" field, shared by registration and password reset.
 * It is not mapped: the plain password never reaches the entity.
 *
 * @extends AbstractType<string>
 */
final class NewPasswordType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => [
                'label' => 'Mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'Confirmer le mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'invalid_message' => 'Les deux mots de passe doivent être identiques.',
            'constraints' => [
                new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                new Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
            ],
        ]);
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }
}
