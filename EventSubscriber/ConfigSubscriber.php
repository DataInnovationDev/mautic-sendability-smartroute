<?php

declare(strict_types=1);

namespace MauticPlugin\SendabilitySmartRouteBundle\EventSubscriber;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\ConfigBundle\Event\ConfigEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\Dsn\Dsn;
use MauticPlugin\SendabilitySmartRouteBundle\Form\Type\SmartRouteConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
            ConfigEvents::CONFIG_PRE_SAVE    => ['onConfigBeforeSave', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm([
            'bundle'     => 'SendabilitySmartRouteBundle',
            'formType'   => SmartRouteConfigType::class,
            'formAlias'  => 'smartrouteconfig',
            'formTheme'  => '@SendabilitySmartRoute/FormTheme/Config/_config_smartrouteconfig_widget.html.twig',
            'parameters' => $event->getParametersFromConfig('SendabilitySmartRouteBundle'),
        ]);
    }

    public function onConfigBeforeSave(ConfigEvent $event): void
    {
        $data = $event->getConfig('smartrouteconfig');

        if (empty($data)) {
            return;
        }

        // Preserve existing DSN password if the new one is empty
        if (isset($data['smartroute_secondary_dsn'])) {
            $newDsn     = (string) $data['smartroute_secondary_dsn'];
            $currentDsn = (string) $this->coreParametersHelper->get('smartroute_secondary_dsn');

            if ('' !== $currentDsn && '' !== $newDsn) {
                try {
                    $newParsed     = Dsn::fromString($newDsn);
                    $currentParsed = Dsn::fromString($currentDsn);

                    // If password is empty in the new DSN but exists in current, preserve it
                    if (null === $newParsed->getPassword() && null !== $currentParsed->getPassword()) {
                        $data['smartroute_secondary_dsn'] = (string) $newParsed->setPassword($currentParsed->getPassword());
                    }
                } catch (\InvalidArgumentException) {
                    // DSN parsing failed, just keep the submitted value
                }
            }
        }

        $event->setConfig($data, 'smartrouteconfig');
    }
}
