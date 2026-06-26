<?php

declare(strict_types=1);

namespace MauticPlugin\SendabilitySmartRouteBundle\EventSubscriber;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use MauticPlugin\SendabilitySmartRouteBundle\Service\RoutingResolver;
use MauticPlugin\SendabilitySmartRouteBundle\Service\TransportInjector;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mime\Address;

class SmartRouteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TransportInjector $transportInjector,
        private RoutingResolver $routingResolver,
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_PRE_SEND => ['onEmailPreSend', 100],
        ];
    }

    public function onEmailPreSend(EmailSendEvent $event): void
    {
        // Skip if no helper (content-generation-only / dynamic content parsing)
        if (null === $event->getHelper()) {
            return;
        }

        $transportName = $this->transportInjector->getTransportName();
        $message       = $event->getHelper()->message;
        $headers       = $message->getHeaders();

        // Mautic reuses a single message object across recipients/batches and only
        // resets To/Cc/Bcc between sends. Symfony's Transports::send() also RE-ADDS the
        // X-Transport header whenever the secondary transport throws (e.g. Kumo timeout),
        // so a stale "X-Transport: smartroute" can survive onto the next, non-matching
        // message — dragging the default From (crm) onto the secondary transport. Always
        // clear any leftover routing header on the shared message before deciding.
        if ($headers->has('X-Transport')) {
            $headers->remove('X-Transport');
        }

        if (!$this->routingResolver->shouldRouteToSecondary($event)) {
            return;
        }

        if (!$this->transportInjector->ensureTransportRegistered()) {
            $this->logger->warning('[SendabilitySmartRoute] Routing matched but secondary transport could not be registered.');

            return;
        }

        $headers->addTextHeader('X-Transport', $transportName);

        // Override From address if configured for the secondary transport
        $fromEmail = (string) $this->coreParametersHelper->get('smartroute_from_email');
        if ('' !== $fromEmail) {
            $fromName = (string) $this->coreParametersHelper->get('smartroute_from_name');
            // From[0] determines the SMTP envelope MAIL FROM (Symfony derives the envelope
            // sender from the Sender header or the first From address), so overriding From
            // here is what makes the secondary MTA see the mta1 identity as the envelope.
            $message->from(new Address($fromEmail, $fromName ?: ''));
            // Also pin Return-Path to the same identity for consistent bounce attribution.
            $message->returnPath($fromEmail);
            $this->logger->info(sprintf('[SendabilitySmartRoute] From address set to %s <%s>.', $fromName, $fromEmail));
        }

        $lead  = $event->getLead();
        $email = is_array($lead) ? ($lead['email'] ?? 'unknown') : (($lead?->getEmail()) ?? 'unknown');
        $this->logger->info(sprintf('[SendabilitySmartRoute] Routing email to %s via transport "%s".', $email, $transportName));
    }
}
