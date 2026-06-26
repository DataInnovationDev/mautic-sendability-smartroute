<?php

declare(strict_types=1);

namespace MauticPlugin\SendabilitySmartRouteBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\EmailBundle\Mailer\Transport\TransportFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends CommonAjaxController
{
    public function testSmtpAction(Request $request, TransportFactory $transportFactory): JsonResponse
    {
        $dsn = $request->request->get('dsn', '');

        if (empty($dsn)) {
            return $this->sendJsonResponse([
                'success' => 0,
                'message' => 'No DSN provided.',
            ]);
        }

        try {
            $transport = $transportFactory->fromString($dsn);

            return $this->sendJsonResponse([
                'success' => 1,
                'message' => sprintf('Transport created successfully: %s', (string) $transport),
            ]);
        } catch (\Throwable $e) {
            return $this->sendJsonResponse([
                'success' => 0,
                'message' => sprintf('Failed to create transport: %s', $e->getMessage()),
            ]);
        }
    }
}
