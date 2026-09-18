<?php
namespace App\Contact\UI;

use App\Contact\Domain\Subject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'empty_data' => ''])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'empty_data' => ''])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('subject', ChoiceType::class, ['label' => 'Sujet', 'choices' => Subject::choices()])
            ->add('message', TextareaType::class, ['label' => 'Message'])
            // Jeton horodaté et signé : prouve que la page a bien été servie
            // par ce site, et quand. Voir App\Contact\AntiSpam\FormSignature.
            ->add('ts', HiddenType::class, [
                'mapped' => false,
                'data' => $options['form_token'],
            ])
            // Piège à bots, sous un nom imprévisible qui change chaque jour :
            // invisible et hors du parcours clavier, un humain ne le remplit
            // jamais. C'est SubmissionGuard qui en tire les conséquences.
            ->add($options['honeypot_field'], TextType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'empty_data' => '',
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Le gabarit ne peut pas deviner le nom du jour : on le lui donne.
        $view->vars['honeypot_field'] = $options['honeypot_field'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactFormData::class,
            // Les bots ajoutent volontiers des champs à eux : on les ignore
            // au lieu d'en faire une erreur de validation visible.
            'allow_extra_fields' => true,
        ]);
        $resolver->setRequired(['form_token', 'honeypot_field']);
        $resolver->setAllowedTypes('form_token', 'string');
        $resolver->setAllowedTypes('honeypot_field', 'string');
    }
}
