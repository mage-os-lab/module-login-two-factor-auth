<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Model;

/**
 * One-time recovery codes. Only SHA-256 hashes are stored: the codes carry 50 bits of
 * randomness each, so a slow password hash would add nothing but latency.
 */
class RecoveryCodes
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const LENGTH = 10;

    /**
     * @return string[] plain codes formatted as xxxxx-xxxxx
     */
    public function generate(int $count): array
    {
        $codes = [];
        $max = strlen(self::ALPHABET) - 1;
        while (count($codes) < $count) {
            $raw = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $raw .= self::ALPHABET[random_int(0, $max)];
            }
            $codes[$raw] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        }

        return array_values($codes);
    }

    /**
     * @param string[] $codes
     * @return string[]
     */
    public function hashAll(array $codes): array
    {
        return array_map(fn (string $code): string => $this->hash($code), $codes);
    }

    /**
     * Returns the remaining hashes when $code matches one of them, null otherwise.
     *
     * @param string[] $hashes
     * @return string[]|null
     */
    public function consume(array $hashes, string $code): ?array
    {
        $normalized = $this->normalize($code);
        if (strlen($normalized) !== self::LENGTH) {
            return null;
        }

        $candidate = $this->hash($normalized);
        $matchedIndex = null;
        foreach ($hashes as $index => $hash) {
            if (hash_equals((string) $hash, $candidate)) {
                $matchedIndex = $index;
            }
        }
        if ($matchedIndex === null) {
            return null;
        }
        unset($hashes[$matchedIndex]);

        return array_values($hashes);
    }

    public function looksLikeRecoveryCode(string $code): bool
    {
        return strlen($this->normalize($code)) === self::LENGTH && !ctype_digit($this->normalize($code));
    }

    private function hash(string $code): string
    {
        return hash('sha256', $this->normalize($code));
    }

    private function normalize(string $code): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $code));
    }
}
