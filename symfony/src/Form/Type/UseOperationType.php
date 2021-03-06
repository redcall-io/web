<?php

namespace App\Form\Type;

use App\Form\Model\Operation;
use App\Security\Helper\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class UseOperationType extends AbstractType
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('structureExternalId', ChoiceType::class, [
                'label'       => 'form.operation.fields.structure_use',
                'choices'     => array_flip($this->security->getUser()->getStructuresAsList()),
                'required'    => false,
                'constraints' => [
                    new NotBlank(),
                    new Choice(['choices' => array_flip($this->security->getUser()->getStructuresAsList())]),
                ],
            ])
            ->add('operation', ChoiceType::class, [
                'label'    => 'form.operation.fields.operation_use',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('operationExternalId', TextType::class, [
                'label'       => 'form.operation.fields.operation_id',
                'constraints' => [
                    new NotBlank(),
                ],
            ]);

        // The ChoiceToValue transformer search for entries on the empty pre-declared list
        // when form is submitted, so we jsut disable it to allow multiple values.
        $builder->get('operation')->resetViewTransformers();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Operation::class,
        ]);
    }
}