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
}
