<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Ticket;
use AppBundle\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;

/**
 * Class TicketType
 */
class TicketType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('floor', TextType::class, [
                'label' => 'admin.form.ticket.floor',
                'required' => false,
            ])
            ->add('door', TextType::class, [
                'label' => 'admin.form.ticket.door',
                'required' => false,
            ])
            ->add('number', TextType::class, [
                'label' => 'admin.form.ticket.number',
                'required' => false,
            ])
            ->add('user', BeneficiaryType::class, [
                'label' => 'admin.form.booking.event',
                'data_class' => User::class,
            ])
            ->add('delete', ButtonType::class, ['attr' => ['class' => 'delete-ticket']]);
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Ticket::class,
        ]);
    }
}
