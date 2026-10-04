<?php

namespace App\Security\Form;

use App\Security\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public const PASSWORD_MIN_LENGTH = 8;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                // Champ vide : chaîne vide plutôt que null.
                'empty_data' => '',
                'attr' => ['autocomplete' => 'email', 'autofocus' => true],
                'constraints' => [
                    new NotBlank(message: 'Saisissez votre adresse e-mail.'),
                    new Email(message: 'Cette adresse e-mail n\'est pas valide.'),
                    new Length(max: 180, maxMessage: 'Cette adresse e-mail est trop longue.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                // Non copié dans l'entité : seul le hachage est conservé.
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                    'help' => sprintf('%d caractères minimum.', self::PASSWORD_MIN_LENGTH),
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Choisissez un mot de passe.'),
                    new Length(
                        min: self::PASSWORD_MIN_LENGTH,
                        max: 4096,
                        minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'empty_data' => static fn (FormInterface $form): User => new User((string) $form->get('email')->getData()),
        ]);
    }
}
