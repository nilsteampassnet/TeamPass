<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/** Shared HTTP protections for fixed email-provider endpoints. */
final class EmailHttp
{
    /** Create an HTTP client without a provider SDK or a user-configurable base URL. */
    public static function client(): ClientInterface
    {
        return new Client();
    }

    /** Security options take precedence over any request-specific options. */
    public static function options(EmailSettings $settings, #[\SensitiveParameter] array $options = []): array
    {
        $timeout = max(1, min(60, (int) $settings->timeout));
        return array_merge($options, [
            'verify' => true, 'allow_redirects' => false, 'http_errors' => false,
            'debug' => false, 'stream' => true,
            'timeout' => $timeout, 'connect_timeout' => min(10, $timeout), 'read_timeout' => $timeout,
        ]);
    }

    /** Read bounded JSON and always close the response, without retaining a raw error body. */
    public static function json(ResponseInterface $response): ?array
    {
        $stream = $response->getBody();
        $body = '';
        try {
            while (!$stream->eof() && strlen($body) <= 65536) {
                $chunk = $stream->read(min(8192, 65537 - strlen($body)));
                if ($chunk === '') {
                    break;
                }
                $body .= $chunk;
            }
            if (strlen($body) > 65536) {
                return null;
            }
            $data = json_decode($body, true);
            return is_array($data) ? $data : null;
        } finally {
            $stream->close();
        }
    }

    /** Preserve valid Retry-After seconds or an HTTP date; null lets the queue choose backoff. */
    public static function retryAfter(ResponseInterface $response): ?int
    {
        $header = trim($response->getHeaderLine('Retry-After'));
        if ($header !== '' && ctype_digit($header)) {
            $digits = ltrim($header, '0');
            $maximum = (string) (PHP_INT_MAX - time());
            // Leading zeros are legal. Saturate overflow instead of retrying too early.
            if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
                return (int) $maximum;
            }
            return $digits === '' ? 0 : (int) $digits;
        }
        $date = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $header, new \DateTimeZone('UTC'));
        return $date === false ? null : max(0, $date->getTimestamp() - time());
    }

    /** Distinguish a known curl timeout without exposing exception messages. */
    public static function networkCode(\Throwable $error): string
    {
        if (method_exists($error, 'getHandlerContext') && ($error->getHandlerContext()['errno'] ?? 0) === 28) {
            return 'timeout';
        }
        return 'network';
    }
}
