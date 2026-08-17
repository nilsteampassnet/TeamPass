<?php
/**
 * Tests for CA pinning behavior in makeHttpsCall.
 *
 * Uses namespace-level function overrides to intercept curl calls made by Client,
 * allowing us to verify the actual curl options set by the real makeHttpsCall method.
 */

namespace Duo\DuoUniversal {
    $GLOBALS['_curl_options'] = [];

    function curl_init()
    {
        $GLOBALS['_curl_options'] = [];
        return \curl_init();
    }

    function curl_setopt($ch, $option, $value)
    {
        $GLOBALS['_curl_options'][$option] = $value;
        if ($option === CURLOPT_CAPATH && ($GLOBALS['_force_capath_failure'] ?? false)) {
            return false;
        }
        return \curl_setopt($ch, $option, $value);
    }

    function curl_exec($ch)
    {
        return json_encode(["stat" => "OK", "response" => ["timestamp" => 1234567890]]);
    }

    function curl_getinfo($ch, $opt = null)
    {
        if ($opt === CURLINFO_HTTP_CODE) {
            return 200;
        }
        return 0;
    }
}

namespace Duo\Tests {
    use Duo\DuoUniversal\Client;
    use PHPUnit\Framework\TestCase;

    final class CAPinningTest extends TestCase
    {
        private $client_id = "12345678901234567890";
        private $client_secret = "1234567890123456789012345678901234567890";
        private $api_host = "api-123456.duo.com";
        private $redirect_url = "https://redirect_example.com";

        public function testCaInfoSetWhenPinningEnabled(): void
        {
            $client = new Client(
                $this->client_id,
                $this->client_secret,
                $this->api_host,
                $this->redirect_url
            );
            $client->healthCheck();

            $this->assertArrayHasKey(CURLOPT_CAINFO, $GLOBALS['_curl_options']);
            $this->assertEquals(Client::DUO_CERTS, $GLOBALS['_curl_options'][CURLOPT_CAINFO]);
        }

        public function testCaPathBlocksOsFallbackWhenPinningEnabled(): void
        {
            $client = new Client(
                $this->client_id,
                $this->client_secret,
                $this->api_host,
                $this->redirect_url
            );
            $client->healthCheck();

            $this->assertArrayHasKey(CURLOPT_CAPATH, $GLOBALS['_curl_options']);
            $this->assertMatchesRegularExpression(
                '#^/dev/null/[0-9a-f]{32}$#',
                $GLOBALS['_curl_options'][CURLOPT_CAPATH]
            );
        }

        public function testCaInfoNotSetWhenPinningDisabled(): void
        {
            $client = new Client(
                $this->client_id,
                $this->client_secret,
                $this->api_host,
                $this->redirect_url,
                true,
                null,
                true
            );
            $client->healthCheck();

            $this->assertArrayNotHasKey(CURLOPT_CAINFO, $GLOBALS['_curl_options']);
        }

        public function testCaPathNotSetWhenPinningDisabled(): void
        {
            $client = new Client(
                $this->client_id,
                $this->client_secret,
                $this->api_host,
                $this->redirect_url,
                true,
                null,
                true
            );
            $client->healthCheck();

            $this->assertArrayNotHasKey(CURLOPT_CAPATH, $GLOBALS['_curl_options']);
        }

        public function testSslVerifyPeerAlwaysEnabledWithPinning(): void
        {
            $client = new Client(
                $this->client_id,
                $this->client_secret,
                $this->api_host,
                $this->redirect_url
            );
            $client->healthCheck();

            $this->assertArrayHasKey(CURLOPT_SSL_VERIFYPEER, $GLOBALS['_curl_options']);
            $this->assertTrue($GLOBALS['_curl_options'][CURLOPT_SSL_VERIFYPEER]);
        }

        public function testSslVerifyPeerAlwaysEnabledWithoutPinning(): void
        {
            $client = new Client(
                $this->client_id,
                $this->client_secret,
                $this->api_host,
                $this->redirect_url,
                true,
                null,
                true
            );
            $client->healthCheck();

            $this->assertArrayHasKey(CURLOPT_SSL_VERIFYPEER, $GLOBALS['_curl_options']);
            $this->assertTrue($GLOBALS['_curl_options'][CURLOPT_SSL_VERIFYPEER]);
        }

        public function testHttpsProtocolRestriction(): void
        {
            $client = new Client(
                $this->client_id,
                $this->client_secret,
                $this->api_host,
                $this->redirect_url
            );
            $client->healthCheck();

            $this->assertArrayHasKey(CURLOPT_PROTOCOLS, $GLOBALS['_curl_options']);
            $this->assertEquals(CURLPROTO_HTTPS, $GLOBALS['_curl_options'][CURLOPT_PROTOCOLS]);
        }

        public function testCaPathSetFailureThrows(): void
        {
            $GLOBALS['_force_capath_failure'] = true;
            try {
                $this->expectException(\Duo\DuoUniversal\DuoException::class);
                $this->expectExceptionMessage("Failed to set CURLOPT_CAPATH");
                $client = new Client(
                    $this->client_id,
                    $this->client_secret,
                    $this->api_host,
                    $this->redirect_url
                );
                $client->healthCheck();
            } finally {
                $GLOBALS['_force_capath_failure'] = false;
            }
        }
    }
}
