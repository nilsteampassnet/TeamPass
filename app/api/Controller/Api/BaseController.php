<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version    API
 *
 * @file      BaseController.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2026 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */


use Symfony\Component\HttpFoundation\Request AS symfonyRequest;

class BaseController
{
    /**
     * Standard reason phrases for the HTTP status codes used by the API (RFC 9110).
     */
    public const HTTP_REASONS = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    /**
     * __call magic method.
     */
    public function __call($name, $arguments)
    {
        $this->sendProblem(404, 'Unknown route');
    }

    /**
     * Build a status line with the standard reason phrase (RFC 9110 — custom
     * reason phrases are vestigial and must not carry information).
     *
     * @param int $status HTTP status code
     * @return string
     */
    protected function statusLine(int $status): string
    {
        return 'HTTP/1.1 ' . $status . ' ' . (self::HTTP_REASONS[$status] ?? 'Error');
    }

    /**
     * Mark functional API usage for inactivity management.
     *
     * Authentication, token refresh and settings refresh deliberately do not call
     * this helper. Only user-visible actions should refresh inactivity state.
     */
    protected function markApiFunctionalActivity(array $userData): void
    {
        $userId = (int) ($userData['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        if (function_exists('markUserFunctionalActivity') === false) {
            require_once API_ROOT_PATH . '/../sources/main.functions.php';
        }

        markUserFunctionalActivity($userId, 'api');
    }

    /**
     * Send an RFC 9457 application/problem+json error response.
     *
     * The legacy 'error' member is kept alongside 'title'/'detail' so existing
     * clients (browser extension, scripts) keep working for one major version.
     *
     * @param int    $status       HTTP status code
     * @param string $detail       Human-readable explanation of the error
     * @param array  $extraHeaders Additional headers (e.g. 'Allow: GET' on 405)
     */
    protected function sendProblem(int $status, string $detail, array $extraHeaders = []): void
    {
        $this->sendOutput(
            json_encode([
                'type' => 'about:blank',
                'title' => self::HTTP_REASONS[$status] ?? 'Error',
                'status' => $status,
                'detail' => $detail,
                'error' => $detail,
            ]),
            array_merge(
                ['Content-Type: application/problem+json; charset=UTF-8', $this->statusLine($status)],
                $extraHeaders
            )
        );
    }

    /**
     * Send a problem response from a legacy 'HTTP/1.1 <code> <text>' header string
     * (models still return error_header strings) — the status code is extracted
     * and the reason phrase replaced by the standard one.
     *
     * @param string $statusHeader Legacy status line, e.g. 'HTTP/1.1 404 Not Found'
     * @param string $detail       Human-readable explanation of the error
     * @param array  $extraHeaders Additional headers
     */
    protected function sendProblemFromHeader(string $statusHeader, string $detail, array $extraHeaders = []): void
    {
        $status = preg_match('/\b([1-5]\d{2})\b/', $statusHeader, $matches) === 1
            ? (int) $matches[1]
            : 500;
        $this->sendProblem($status, $detail, $extraHeaders);
    }

    /**
     * Get URI elements.
     *
     * Supports both PATH_INFO style (/api/index.php/controller/action)
     * and clean URLs via mod_rewrite (/api/controller/action).
     * Uses SCRIPT_NAME to strip the base path so subdirectory installs work correctly.
     *
     * @return array|string
     */
    public function getUriSegments()
    {
        $request = symfonyRequest::createFromGlobals();
        $requestUri = $request->getRequestUri();

        $parsed = parse_url($requestUri, PHP_URL_PATH);
        $uriPath = is_string($parsed) ? $parsed : '';

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        if ($scriptName !== '' && str_starts_with($uriPath, $scriptName)) {
            // PATH_INFO style: /api/index.php/controller/action
            $uriPath = substr($uriPath, strlen($scriptName));
        } else {
            // Clean URL via mod_rewrite: /api/controller/action
            // Strip the script directory (e.g. /api) to get route segments only.
            $scriptDir = rtrim(dirname($scriptName), '/\\');
            if ($scriptDir !== '' && $scriptDir !== '.' && str_starts_with($uriPath, $scriptDir . '/')) {
                $uriPath = substr($uriPath, strlen($scriptDir));
            }
        }

        $parts = array_values(array_filter(explode('/', $uriPath)));
        // Transparently strip the /api/v1/ prefix — only v1 exists today. Unknown
        // versions (e.g. /v2/) are NOT stripped: the segment falls through to the
        // router, which returns 404 instead of silently behaving as v1.
        if (!empty($parts) && $parts[0] === 'v1') {
            array_shift($parts);
        }
        return $this->sanitizeUrl($parts);
    }

    /**
     * Get querystring params.
     *
     * @return array|string
     */
    public function getQueryStringParams()
    {
        $request = symfonyRequest::createFromGlobals();

        // Priority 1: JSON body — returned as-is (no HTML escaping; passwords must not be mangled)
        if ($request->getContentTypeFormat() === 'json') {
            return $request->toArray();
        }

        // Priority 2: POST form data — trim only, no HTML escaping
        if ($request->getMethod() === 'POST' && $request->request->count() > 0) {
            return $this->trimParams($request->request->all());
        }

        // Priority 3: Query string — trim only, no HTML escaping
        $queryString = $request->getQueryString();
        if (!empty($queryString)) {
            parse_str(html_entity_decode($queryString), $query);
            return $this->trimParams($query);
        }

        return [];
    }

    /**
     * Recursively trim string values in an array (no HTML escaping).
     *
     * @param array $params
     * @return array
     */
    private function trimParams(array $params): array
    {
        return array_map(function ($v) {
            if (is_string($v)) {
                return trim($v);
            }
            if (is_array($v)) {
                return $this->trimParams($v);
            }
            return $v;
        }, $params);
    }

    /**
     * Sanitize URL elements.
     *
     * @param array $array
     * @return array|string
     */
    public function sanitizeUrl(array $array)
    {
        $filters = [];
        $array_size = count($array);
        for ($i=0; $i < $array_size; $i++) {
            array_push($filters, 'trim|escape');
        }
        
        return dataSanitizer(
            $array,
            $filters
        );
    }


    /**
     * Send API output.
     *
     * @param mixed  $data
     * @param array  $httpHeaders
     */
    protected function sendOutput($data, array $httpHeaders=[]): void
    {
        header_remove('Set-Cookie');

        if (count($httpHeaders) > 0) {
            foreach ($httpHeaders as $httpHeader) {
                header($httpHeader);
            }
        }

        echo $data;
    }
}
