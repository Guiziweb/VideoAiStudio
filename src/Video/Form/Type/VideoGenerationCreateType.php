<?php

declare(strict_types=1);

namespace App\Video\Form\Type;

use App\Video\Entity\VideoGeneration;
use App\Video\Validator\Constraints\SufficientTokens;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class VideoGenerationCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prompt', TextareaType::class, [
                'label' => 'app.ui.video_prompt_label',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'app.ui.video_prompt_placeholder',
                    'style' => 'resize: vertical;',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'app.ui.form.prompt_required',
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 1000,
                        'minMessage' => 'app.ui.form.prompt_min_length',
                        'maxMessage' => 'app.ui.form.prompt_max_length',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VideoGeneration::class,
            'constraints' => [
                new SufficientTokens(),
            ],
        ]);
    }
}
