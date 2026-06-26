<?php

declare(strict_types=1);

namespace MauticPlugin\SendabilitySmartRouteBundle\Form\Type;

use Mautic\ConfigBundle\Form\Type\DsnType;
use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class SmartRouteConfigType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'smartroute_enabled',
            YesNoButtonGroupType::class,
            [
                'label' => 'sendability.smartroute.config.enabled',
                'data'  => (bool) ($options['data']['smartroute_enabled'] ?? false),
                'attr'  => [
                    'tooltip' => 'sendability.smartroute.config.enabled.tooltip',
                ],
            ]
        );

        $builder->add(
            'smartroute_secondary_dsn',
            DsnType::class,
            [
                'label'    => 'sendability.smartroute.config.secondary_dsn',
                'required' => false,
                'attr'     => [
                    'tooltip' => 'sendability.smartroute.config.secondary_dsn.tooltip',
                ],
            ]
        );

        $builder->add(
            'smartroute_mode',
            ChoiceType::class,
            [
                'label'   => 'sendability.smartroute.config.mode',
                'choices' => [
                    'sendability.smartroute.config.mode.domain'       => 'domain',
                    'sendability.smartroute.config.mode.custom_field' => 'custom_field',
                ],
                'attr'    => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.sendabilityToggleMode(this)',
                    'tooltip'  => 'sendability.smartroute.config.mode.tooltip',
                ],
            ]
        );

        $builder->add(
            'smartroute_secondary_percentage',
            IntegerType::class,
            [
                'label'    => 'sendability.smartroute.config.secondary_percentage',
                'required' => false,
                'data'     => (int) ($options['data']['smartroute_secondary_percentage'] ?? 100),
                'attr'     => [
                    'class'   => 'form-control',
                    'min'     => 0,
                    'max'     => 100,
                    'tooltip' => 'sendability.smartroute.config.secondary_percentage.tooltip',
                ],
            ]
        );

        $builder->add(
            'smartroute_domain_list',
            TextareaType::class,
            [
                'label'    => 'sendability.smartroute.config.domain_list',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 4,
                    'placeholder' => 'gmail.com, yahoo.com, hotmail.com, outlook.com',
                    'tooltip'     => 'sendability.smartroute.config.domain_list.tooltip',
                ],
            ]
        );

        $builder->add(
            'smartroute_custom_field',
            TextType::class,
            [
                'label'    => 'sendability.smartroute.config.custom_field',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'placeholder' => 'e.g. smtp_route',
                    'tooltip'     => 'sendability.smartroute.config.custom_field.tooltip',
                ],
            ]
        );

        $builder->add(
            'smartroute_field_value',
            TextType::class,
            [
                'label'    => 'sendability.smartroute.config.field_value',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'placeholder' => 'e.g. secondary',
                    'tooltip'     => 'sendability.smartroute.config.field_value.tooltip',
                ],
            ]
        );

        $builder->add(
            'smartroute_from_email',
            TextType::class,
            [
                'label'    => 'sendability.smartroute.config.from_email',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'placeholder' => 'e.g. noreply@yourdomain.com',
                    'tooltip'     => 'sendability.smartroute.config.from_email.tooltip',
                ],
            ]
        );

        $builder->add(
            'smartroute_from_name',
            TextType::class,
            [
                'label'    => 'sendability.smartroute.config.from_name',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'placeholder' => 'e.g. My Company',
                    'tooltip'     => 'sendability.smartroute.config.from_name.tooltip',
                ],
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'smartrouteconfig';
    }
}
