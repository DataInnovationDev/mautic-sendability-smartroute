<?php

declare(strict_types=1);

namespace MauticPlugin\SendabilitySmartRouteBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Mailer\Transport\TransportFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

class TransportInjector
{
    private const TRANSPORT_NAME = 'smartroute';

    private bool $injected = false;

    public function __construct(
        private MailerInterface $mailer,
        private TransportFactory $transportFactory,
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Lazily creates and injects the secondary transport into Symfony's Transports container.
     * Uses the same reflection pattern as MailHelper::getTransport() (line 1607-1622).
     */
    public function ensureTransportRegistered(): bool
    {
        if ($this->injected) {
            return true;
        }

        $dsn = (string) $this->coreParametersHelper->get('smartroute_secondary_dsn');
        if ('' === $dsn) {
            return false;
        }

        try {
            $newTransport = $this->transportFactory->fromString($dsn);

            // Get the Transports container from the Mailer via reflection
            $mailerRef     = new \ReflectionClass($this->mailer);
            $transportProp = $mailerRef->getProperty('transport');
            $transportsObj = $transportProp->getValue($this->mailer);

            // Access the internal transports array
            $transportsRef = new \ReflectionClass($transportsObj);
            $arrayProp     = $transportsRef->getProperty('transports');
            $array         = $arrayProp->getValue($transportsObj);

            // Add our transport under the 'smartroute' key
            $array[self::TRANSPORT_NAME] = $newTransport;
            $arrayProp->setValue($transportsObj, $array);

            $this->injected = true;
            $this->logger->info('[SendabilitySmartRoute] Secondary transport registered successfully.');

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('[SendabilitySmartRoute] Failed to register secondary transport: '.$e->getMessage());

            return false;
        }
    }

    public function getTransportName(): string
    {
        return self::TRANSPORT_NAME;
    }
}
