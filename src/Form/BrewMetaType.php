<?php
declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class BrewMetaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('coffee', TextType::class, ['required' => false])
            ->add('basket', ChoiceType::class, ['choices' => [
                "" => null,
                "Rancilio Single" => 'rancilio_single',
                "Rancilio Double" => 'rancilio_double',
                "Rancilio Triple" => 'rancilio_triple',
                "IMS Nanotech 15g" => 'ims_nanotech_15g',
                "E&B Competition 18/20g" => 'eb_competition_1820g',
            ]])
            ->add('grindSize', IntegerType::class, ['required' => false])
            ->add('dose', NumberType::class, ['required' => false])
            ->add('tastingNotes', TextareaType::class, ['required' => false])
            ->add('rating', IntegerType::class, ['required' => false])
            ->add('save', SubmitType::class)
        ;
    }
}
