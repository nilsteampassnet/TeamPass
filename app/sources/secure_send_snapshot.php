<?php

declare(strict_types=1);

/**
 * Serialize a coherent item copy within the existing OTV TEXT column.
 *
 * Defuse v2 adds 84 bytes and returns hex, so JSON must fit in the remaining
 * half-column budget. Only description may be shortened; credentials never are.
 *
 * @param array $item Authorized item as stored in TeamPass
 * @param string $password Decrypted password
 * @return array{plaintext:string, description_truncated:bool}
 * @throws InvalidArgumentException When required fields exceed the storage budget or contain invalid UTF-8
 */
function secureSendEncodeSnapshot(array $item, string $password): array
{
    $payload = [
        'label' => (string) $item['label'], 'login' => (string) $item['login'],
        'url' => (string) $item['url'], 'password' => $password,
        'description' => (string) $item['description'], 'description_truncated' => false,
    ];
    $budget = intdiv(65535, 2) - \Defuse\Crypto\Core::MINIMUM_CIPHERTEXT_SIZE;
    $encode = static fn (array $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try {
        $plaintext = $encode($payload);
        if (strlen($plaintext) <= $budget) {
            return ['plaintext' => $plaintext, 'description_truncated' => false];
        }
        $description = $payload['description'];
        $payload['description'] = '';
        $payload['description_truncated'] = true;
        if (strlen($encode($payload)) > $budget) {
            throw new InvalidArgumentException('invalid_payload');
        }
        // Search byte lengths, cutting only at UTF-8 boundaries and measuring escaped JSON.
        $low = 0;
        $high = min(strlen($description), $budget);
        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            $payload['description'] = mb_strcut($description, 0, $middle, 'UTF-8');
            if (strlen($encode($payload)) <= $budget) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }
        $payload['description'] = mb_strcut($description, 0, $low, 'UTF-8');
        return ['plaintext' => $encode($payload), 'description_truncated' => true];
    } catch (JsonException $e) {
        throw new InvalidArgumentException('invalid_payload');
    }
}
