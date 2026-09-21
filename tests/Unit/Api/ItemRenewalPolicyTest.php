<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../app/api/Model/ItemModel.php';

/** Exercise API creation normalization without database or encryption side effects. */
class ItemRenewalPolicyTest extends TestCase
{
    private function prepare(array $policy): array
    {
        $model = (new ReflectionClass(ItemModel::class))->newInstanceWithoutConstructor();
        return (new ReflectionMethod($model, 'prepareData'))->invoke($model, $policy + [
            'folder_id' => 7, 'label' => 'Database', 'password' => 'secret',
            'totp_algorithm' => 'sha1', 'totp_digits' => 6, 'totp_period' => 30,
        ]);
    }

    public function testOmittedPolicyIsDisabledAndExplicitDaysAreNormalized(): void
    {
        self::assertSame(0, $this->prepare([])['renewalPeriod']);
        self::assertSame(0, $this->prepare(['renewal_period' => '0'])['renewalPeriod']);
        self::assertSame(30, $this->prepare(['renewal_period' => '30'])['renewalPeriod']);
    }

    public function testMalformedPolicyCannotReachCreation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->prepare(['renewal_period' => '30 days']);
    }

    /** Execute update normalization with both live LAPR roles, including an unchanged dormant policy. */
    public function testLaprUpdateRejectsPolicyChangesButAllowsOmittedAndUnchangedValues(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../app/api/Model/ItemModel.php');
        $start = strpos($source, "if (array_key_exists('renewal_period', \$params)) {");
        $end = strpos($source, '$fieldsDefinitions =', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $guard = eval('return static function (array $params, bool $laprIsManaged, bool $laprIsCredential): array {'
            . '$currentItem = ["renewal_period" => 30]; $updateData = [];'
            . substr($source, $start, $end - $start) . ' return $updateData; };');
        foreach ([false, true] as $managed) {
            foreach ([false, true] as $credential) {
                self::assertSame([], $guard([], $managed, $credential));
                self::assertSame(['renewal_period' => 30], $guard(['renewal_period' => '30'], $managed, $credential));
                foreach ([0, 90] as $days) {
                    $result = $guard(['renewal_period' => $days], $managed, $credential);
                    if ($managed || $credential) {
                        self::assertTrue($result['error']);
                        self::assertSame('HTTP/1.1 409 Conflict', $result['error_header']);
                    } else {
                        self::assertSame(['renewal_period' => $days], $result);
                    }
                }
            }
        }
    }
}
