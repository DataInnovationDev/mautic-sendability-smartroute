<?php

declare(strict_types=1);

namespace MauticPlugin\SendabilitySmartRouteBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\LeadBundle\Entity\Lead;

class RoutingResolver
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
    ) {
    }

    public function shouldRouteToSecondary(EmailSendEvent $event): bool
    {
        if (!$this->coreParametersHelper->get('smartroute_enabled')) {
            return false;
        }

        $mode = (string) $this->coreParametersHelper->get('smartroute_mode', 'domain');

        $matched = match ($mode) {
            'domain'       => $this->matchesDomain($event),
            'custom_field' => $this->matchesCustomField($event),
            default        => false,
        };

        if (!$matched) {
            return false;
        }

        return $this->passesPercentageCheck($event);
    }

    /**
     * Deterministic percentage gate — same contact always hits the same transport,
     * but overall ~X% of matching contacts are routed to secondary.
     * 100 = all matching (default behaviour), 0 = none.
     */
    private function passesPercentageCheck(EmailSendEvent $event): bool
    {
        $percentage = (int) $this->coreParametersHelper->get('smartroute_secondary_percentage', 100);

        if ($percentage >= 100) {
            return true;
        }
        if ($percentage <= 0) {
            return false;
        }

        // Use the recipient email as a stable hash key so the same contact
        // always lands on the same transport across every send in a campaign.
        $email = $this->getRecipientEmail($event) ?? '';

        return (abs(crc32(strtolower($email))) % 100) < $percentage;
    }

    private function matchesDomain(EmailSendEvent $event): bool
    {
        $domainList = (string) $this->coreParametersHelper->get('smartroute_domain_list');
        if ('' === $domainList) {
            return false;
        }

        $domains = array_map(
            fn (string $d) => strtolower(trim($d)),
            explode(',', $domainList)
        );
        $domains = array_filter($domains);

        if (empty($domains)) {
            return false;
        }

        // Get recipient email from the lead data
        $recipientDomain = $this->getRecipientDomain($event);
        if (null === $recipientDomain) {
            return false;
        }

        return in_array($recipientDomain, $domains, true);
    }

    private function matchesCustomField(EmailSendEvent $event): bool
    {
        $fieldAlias = (string) $this->coreParametersHelper->get('smartroute_custom_field');
        $fieldValue = (string) $this->coreParametersHelper->get('smartroute_field_value');

        if ('' === $fieldAlias) {
            return false;
        }

        $lead = $event->getLead();
        if (null === $lead) {
            return false;
        }

        $contactValue = null;
        if ($lead instanceof Lead) {
            $contactValue = $lead->getFieldValue($fieldAlias);
        } elseif (is_array($lead)) {
            $contactValue = $lead[$fieldAlias] ?? null;
        }

        if (null === $contactValue) {
            return false;
        }

        return strtolower((string) $contactValue) === strtolower($fieldValue);
    }

    private function getRecipientEmail(EmailSendEvent $event): ?string
    {
        $lead = $event->getLead();

        if ($lead instanceof Lead) {
            $email = $lead->getEmail();
        } elseif (is_array($lead)) {
            $email = $lead['email'] ?? null;
        } else {
            $email = null;
        }

        if (empty($email) && null !== $event->getHelper()) {
            $to = $event->getHelper()->message->getTo();
            if (!empty($to)) {
                $email = $to[0]->getAddress();
            }
        }

        return (!empty($email) && str_contains($email, '@')) ? $email : null;
    }

    private function getRecipientDomain(EmailSendEvent $event): ?string
    {
        $email = $this->getRecipientEmail($event);
        if (null === $email) {
            return null;
        }

        return strtolower(substr($email, strrpos($email, '@') + 1));
    }
}
