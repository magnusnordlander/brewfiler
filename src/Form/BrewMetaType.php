<?php
declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
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
            ->add('grindSize', IntegerType::class, ['required' => false])
            ->add('dose', NumberType::class, ['required' => false])
            ->add('tastingNotes', TextareaType::class, ['required' => false])
            ->add('save', SubmitType::class)
        ;
    }
}
