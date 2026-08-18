<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel test: rules the shared confirmation dialog relies on.
 *
 * launchConfirmDialog() drives the single #warningModal every page shares. Two mistakes
 * are easy to make there and impossible to see in a diff, hence this guard.
 */
class ConfirmDialogSentinelTest extends TestCase
{
    /**
     * List the served and the source copy of functions.js.
     *
     * @return array<int, string>
     */
    private function functionsJsCopies(): array
    {
        $root = dirname(__DIR__, 2);

        return [
            $root . '/app/includes/js/functions.js',
            $root . '/public/assets/js/functions.js',
        ];
    }

    /**
     * Other flows bind their own DELEGATED handlers on document for #warningModalButtonAction
     * (load.js.php, users.js.php, items.js.php, ...). The .off('click') launchConfirmDialog()
     * does on the button only reaches handlers bound directly to the element, so a stale
     * delegated one would run alongside the confirm callback — extending the session or
     * restarting a re-encryption while the user only meant to confirm a deletion.
     * Stopping the propagation is what keeps the dialog self-contained.
     */
    public function testConfirmDialogStopsThePropagationOfItsActionClick(): void
    {
        foreach ($this->functionsJsCopies() as $path) {
            $source = (string) file_get_contents($path);

            $start = strpos($source, 'function launchConfirmDialog(');
            $this->assertNotFalse($start, 'launchConfirmDialog() is missing from ' . basename($path));

            $body = substr($source, $start);

            $this->assertStringContainsString(
                'event.stopPropagation();',
                $body,
                'launchConfirmDialog() must stop the propagation of the action click, otherwise '
                    . 'delegated handlers left on document by other flows also fire (' . $path . ')'
            );
        }
    }

    /**
     * Labels reach the dialog as JavaScript string literals. addslashes() escapes quotes and
     * nothing else, so a translation carrying a newline or a closing script tag breaks the
     * page. json_encode() with the JSON_HEX_* flags is the encoding every call site must use.
     */
    public function testConfirmDialogCallSitesEncodeTheirLabelsWithJsonEncode(): void
    {
        $root  = dirname(__DIR__, 2);
        $pages = glob($root . '/app/pages/*.js.php');
        $this->assertNotEmpty($pages, 'No page script found');

        foreach ($pages as $page) {
            $source = (string) file_get_contents($page);
            $offset = 0;

            while (($start = strpos($source, 'launchConfirmDialog(', $offset)) !== false) {
                $offset = $start + 1;

                // The title and the message are the two first arguments, one per line.
                $arguments = substr($source, $start, 400);
                $lines     = array_slice(explode("\n", $arguments), 1, 2);

                foreach ($lines as $line) {
                    $this->assertStringNotContainsString(
                        'addslashes(',
                        $line,
                        'launchConfirmDialog() labels must be emitted with json_encode() and the '
                            . 'JSON_HEX_* flags, not addslashes() (' . basename($page) . ')'
                    );
                }
            }
        }
    }
}
