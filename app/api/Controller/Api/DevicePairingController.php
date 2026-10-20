<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 TeamPass.net
 * SPDX-License-Identifier: GPL-3.0-only
 * Distributed without any warranty. See LICENSE.md.
 */

require_once __DIR__ . '/../../../sources/device_pairing.functions.php';

class DevicePairingController extends BaseController
{
    /** Advertise the experimental protocol without exposing account information. */
    public function capabilitiesAction(): void
    {
        $this->execute('capabilities');
    }

    /** Reserve an immutable claim with the scanned invitation secret. */
    public function claimAction(): void
    {
        $this->execute('claim');
    }

    /** Return state only to the verifier holder. */
    public function statusAction(): void
    {
        $this->execute('status');
    }

    /** Atomically activate a credential and deliver its protected envelope. */
    public function consumeAction(): void
    {
        $this->execute('consume');
    }

    /** Bounded, body-only transport common to the unauthenticated device endpoints. */
    private function execute(string $action): void
    {
        header('Cache-Control: no-store');
        header_remove('Access-Control-Allow-Origin');
        try {
            $settings = apiDeviceCurrentSettings([]);
            // Network trust configuration is read through ConfigManager, never from request input.
            $config = new \TeampassClasses\ConfigManager\ConfigManager();
            $settings += $config->getAllSettings();
            devicePairingRequireHttps($_SERVER, $settings);
            $expected = $action === 'capabilities' ? 'GET' : 'POST';
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== $expected) {
                $this->sendProblem(405, 'Method not allowed', ['Allow: ' . $expected]);
                return;
            }
            if ((int) ($settings['device_pairing_schema'] ?? 0) < 2 || (int) ($settings['device_pairing_enabled'] ?? 0) !== 1
                || (int) ($settings['api'] ?? 0) !== 1
            ) {
                throw new DevicePairingException('unavailable', 503);
            }
            if (!empty($_SERVER['QUERY_STRING'])) {
                throw new DevicePairingException('invalid_request');
            }
            $ip = teampassGetClientIpForSecurity($settings);
            devicePairingThrottle('ip:' . (string) ($ip['detected_ip'] ?? ''), 240);
            if ($action === 'capabilities') {
                $result = ['type' => 'teampass.device-pairing', 'version' => 1, 'experimental' => true,
                    'algorithm' => 'RSA-OAEP-256', 'key_bits' => 3072, 'ttl' => TP_DEVICE_PAIRING_TTL,
                    'poll_interval' => TP_DEVICE_PAIRING_POLL_INTERVAL];
            } else {
                $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
                if ($contentType !== 'application/json') {
                    throw new DevicePairingException('invalid_request');
                }
                $raw = file_get_contents('php://input', false, null, 0, 8193);
                if ($raw === false || strlen($raw) > 8192) {
                    throw new DevicePairingException('invalid_request');
                }
                try {
                    $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new DevicePairingException('invalid_request');
                }
                if (!is_array($input) || array_is_list($input)) {
                    throw new DevicePairingException('invalid_request');
                }
                $result = $action === 'claim' ? devicePairingClaim($input, $settings) : devicePairingDeviceAction($action, $input, $settings);
            }
            $this->sendOutput(json_encode($result, JSON_THROW_ON_ERROR), ['Content-Type: application/json; charset=UTF-8', 'HTTP/1.1 200 OK']);
        } catch (DevicePairingException $e) {
            if ($e->getCode() === 429) {
                header('Retry-After: ' . $e->retryAfter);
            }
            $this->sendOutput(json_encode(['type' => 'about:blank', 'title' => 'Pairing request failed', 'status' => $e->getCode(),
                'detail' => 'The pairing request could not be completed.', 'error' => 'The pairing request could not be completed.',
                'reason' => $e->reason]), ['Content-Type: application/problem+json; charset=UTF-8', $this->statusLine($e->getCode())]);
        } catch (Throwable $e) {
            // Exception messages can include SQL and request data; never log or return them.
            error_log('[Device pairing] Request failed');
            $this->sendProblem(500, 'The pairing request could not be completed.');
        }
    }
}
