<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Model;

use Magento\Customer\Model\Session;

/**
 * Holds the "password OK, waiting for the second factor" state in the customer session, and
 * the trusted scope used by code paths allowed to log a customer in without a TOTP challenge.
 */
class LoginGuard
{
    private const SESSION_KEY = 'mageos_tfa_pending';
    public const PENDING_TTL = 300;

    private int $trustedDepth = 0;

    public function __construct(
        private readonly Session $session
    ) {
    }

    /**
     * Runs $callback with the 2FA interception disabled (our own verify step, store switch,
     * "Login as Customer"): the customer was already authenticated by other means.
     */
    public function trusted(callable $callback): mixed
    {
        $this->trustedDepth++;
        try {
            return $callback();
        } finally {
            $this->trustedDepth--;
        }
    }

    public function isTrusted(): bool
    {
        return $this->trustedDepth > 0;
    }

    public function startPending(int $customerId): void
    {
        $this->session->setData(self::SESSION_KEY, [
            'customer_id' => $customerId,
            'created_at' => time(),
            'attempts' => 0,
            'redirect' => null,
            'redirect_force' => false,
        ]);
    }

    /**
     * @return array{customer_id: int, created_at: int, attempts: int, redirect: ?string, redirect_force: bool}|null
     */
    public function getPending(): ?array
    {
        $pending = $this->session->getData(self::SESSION_KEY);
        if (!is_array($pending) || empty($pending['customer_id'])) {
            return null;
        }
        if (time() - (int) $pending['created_at'] > self::PENDING_TTL) {
            $this->clearPending();
            return null;
        }

        return $pending;
    }

    public function hasPending(): bool
    {
        return $this->getPending() !== null;
    }

    /**
     * \ bool $force return there even when "redirect to dashboard after login" is on
     *              (in-page logins: checkout, authentication popup)
     */
    /**
     * @param bool $force return there even when "redirect to dashboard after login" is on
     *                    (in-page logins: checkout, authentication popup)
     */
    public function setPendingRedirect(?string $url, bool $force = false): void
    {
        $pending = $this->getPending();
        if ($pending !== null && $url) {
            $pending['redirect'] = $url;
            $pending['redirect_force'] = $force;
            $this->session->setData(self::SESSION_KEY, $pending);
        }
    }

    /**
     * @return int attempts used so far, including this one
     */
    public function registerFailedAttempt(): int
    {
        $pending = $this->getPending();
        if ($pending === null) {
            return PHP_INT_MAX;
        }
        $pending['attempts']++;
        $this->session->setData(self::SESSION_KEY, $pending);

        return $pending['attempts'];
    }

    public function clearPending(): void
    {
        $this->session->unsetData(self::SESSION_KEY);
    }
}
