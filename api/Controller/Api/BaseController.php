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
 * @copyright 2009-2024 Teampass.net
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
     * @return array|string
     */
    public function getUriSegments()
    {
        $request = symfonyRequest::createFromGlobals();
        $requestUri = $request->getRequestUri();

        $uri = parse_url($requestUri, PHP_URL_PATH);
        $uri = explode( '/', $uri );
        return $this->sanitizeUrl(array_slice($uri, ((int) array_search('index.php', $uri) + 1)));
    }

    /**
     * Get querystring params.
     * 
     * @return array|string
     */
    public function getQueryStringParams()
    {
        $request = symfonyRequest::createFromGlobals();
        $queryString = $request->getQueryString();
        parse_str(html_entity_decode($queryString), $query);
        return $this->sanitizeUrl($query);
    }

    /**
     * Undocumented function
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
     * @param string $httpHeader
     */
    protected function sendOutput($data, $httpHeaders=array()): void
    {
        header_remove('Set-Cookie');

        if (is_array($httpHeaders) && count($httpHeaders)) {
            foreach ($httpHeaders as $httpHeader) {
                header($httpHeader);
            }
        }

        echo $data;
    }
}