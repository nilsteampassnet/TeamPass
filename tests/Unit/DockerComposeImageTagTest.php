<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel test: every TeamPass image tag shipped in docker/docker-compose/ must be `latest`.
 *
 * The Docker quick start downloads docker-compose.yml and .env.example from master and runs
 * them as-is. `.env.example` used to pin TEAMPASS_VERSION=3.1.5.2, a tag that was never
 * published, so the very first `docker compose up` failed at pull (issues #5005, #5402).
 *
 * A pinned default is wrong by construction:
 *   - no release step bumps it, so it goes stale at the next release;
 *   - versioned tags only exist from 3.2.2.0 onward, and there is no rolling `3.2` / `3` tag;
 *   - a versioned tag is frozen, while `latest` is rebuilt weekly with the base image fixes.
 * Pinning is the operator's choice, made in their own .env.
 */
class DockerComposeImageTagTest extends TestCase
{
    private const COMPOSE_DIR = '/docker/docker-compose';

    /**
     * The TEAMPASS_VERSION set in both env files must be `latest`.
     */
    public function testEnvFilesDefaultToLatest(): void
    {
        foreach (['.env.example', '.env'] as $file) {
            $path = dirname(__DIR__, 2) . self::COMPOSE_DIR . '/' . $file;
            $this->assertFileExists($path);

            $matched = preg_match('/^TEAMPASS_VERSION=(.*)$/m', (string) file_get_contents($path), $match);
            $this->assertSame(1, $matched, $file . ' no longer sets TEAMPASS_VERSION');
            $this->assertSame(
                'latest',
                trim($match[1]),
                $file . ' pins TEAMPASS_VERSION to "' . trim($match[1]) . '" — keep "latest" (see this test docblock).'
            );
        }
    }

    /**
     * The fallback tag of every teampass/teampass image reference in the compose files must be `latest`.
     */
    public function testComposeFilesDefaultToLatest(): void
    {
        foreach (['docker-compose.yml', 'docker-compose.with-proxy.yml'] as $file) {
            $path = dirname(__DIR__, 2) . self::COMPOSE_DIR . '/' . $file;
            $this->assertFileExists($path);

            $content = (string) file_get_contents($path);
            $this->assertGreaterThan(
                0,
                preg_match_all('/teampass\/teampass:(\S+)/', $content, $matches),
                $file . ' no longer references the teampass/teampass image'
            );

            foreach ($matches[1] as $reference) {
                $this->assertSame(
                    '${TEAMPASS_VERSION:-latest}',
                    $reference,
                    $file . ' references teampass/teampass:' . $reference . ' — keep ${TEAMPASS_VERSION:-latest}.'
                );
            }
        }
    }
}
