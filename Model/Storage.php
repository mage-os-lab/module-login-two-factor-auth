<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Persistence of the per-customer TOTP state. A row exists only while 2FA is active.
 */
class Storage
{
    public const TABLE = 'mageos_customer_tfa';

    /** @var array<int, array|false> */
    private array $cache = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EncryptorInterface $encryptor,
        private readonly Json $json
    ) {
    }

    public function isActive(int $customerId): bool
    {
        return $this->load($customerId) !== null;
    }

    /**
     * @return array{secret: string, recovery_codes: string[], last_timestep: int, created_at: string}|null
     */
    public function load(int $customerId): ?array
    {
        if (!array_key_exists($customerId, $this->cache)) {
            $connection = $this->resource->getConnection();
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($this->resource->getTableName(self::TABLE))
                    ->where('customer_id = ?', $customerId)
            );
            $this->cache[$customerId] = $row ?: false;
        }

        $row = $this->cache[$customerId];
        if ($row === false) {
            return null;
        }

        return [
            'secret' => $this->encryptor->decrypt((string) $row['secret']),
            'recovery_codes' => $row['recovery_codes'] ? (array) $this->json->unserialize($row['recovery_codes']) : [],
            'last_timestep' => (int) $row['last_timestep'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * @param string[] $recoveryHashes
     */
    public function activate(int $customerId, string $secret, array $recoveryHashes, int $timestep): void
    {
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            [
                'customer_id' => $customerId,
                'secret' => $this->encryptor->encrypt($secret),
                'recovery_codes' => $this->json->serialize(array_values($recoveryHashes)),
                'last_timestep' => $timestep,
            ],
            ['secret', 'recovery_codes', 'last_timestep']
        );
        unset($this->cache[$customerId]);
    }

    public function updateLastTimestep(int $customerId, int $timestep): void
    {
        $connection = $this->resource->getConnection();
        // Conditional update: two concurrent requests with the same code can't both win.
        $affected = $connection->update(
            $this->resource->getTableName(self::TABLE),
            ['last_timestep' => $timestep],
            ['customer_id = ?' => $customerId, 'last_timestep < ?' => $timestep]
        );
        unset($this->cache[$customerId]);
        if ($affected === 0) {
            throw new \RuntimeException('TOTP time-step already used.');
        }
    }

    /**
     * Replaces the recovery codes. With $expected (the list read before), the write only happens
     * if nobody changed it in the meantime, so a recovery code can't be spent twice concurrently.
     *
     * @param string[] $recoveryHashes
     * @param string[]|null $expected
     */
    public function updateRecoveryCodes(int $customerId, array $recoveryHashes, ?array $expected = null): bool
    {
        $where = ['customer_id = ?' => $customerId];
        if ($expected !== null) {
            $where['recovery_codes = ?'] = $this->json->serialize(array_values($expected));
        }
        $affected = $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            ['recovery_codes' => $this->json->serialize(array_values($recoveryHashes))],
            $where
        );
        unset($this->cache[$customerId]);

        return $expected === null || $affected > 0;
    }

    public function delete(int $customerId): bool
    {
        $affected = $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['customer_id = ?' => $customerId]
        );
        unset($this->cache[$customerId]);

        return $affected > 0;
    }
}
