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
     * __call magic method.
     */
    public function __call($name, $arguments)
    {
        $this->sendOutput('', array('HTTP/1.1 404 Not Found'));
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
        
        // Priority 1: JSON body
        if ($request->getContentTypeFormat() === 'json') {
            return $request->toArray();
        }
        
        // Priority 2: POST form data
        if ($request->getMethod() === 'POST' && $request->request->count() > 0) {
            return $this->sanitizeUrl($request->request->all());
        }
        
        // Priority 3: Query string
        $queryString = $request->getQueryString();
        if (!empty($queryString)) {
            parse_str(html_entity_decode($queryString), $query);
            return $this->sanitizeUrl($query);
        }
        
        return [];
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
