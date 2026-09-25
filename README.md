# Mage-OS Login Two-Factor Authentication

`MageOS_LoginTwoFactorAuth` adds opt-in two-factor authentication (TOTP, RFC 6238) for **storefront customers**
on Mage-OS / Magento 2. The core `Magento_TwoFactorAuth` only protects the admin: this module protects the
customer login and lets every customer decide whether to turn it on.

Works with any authenticator app: Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden…

## Installation

```bash
composer require mage-os/module-login-two-factor-auth
bin/magento module:enable MageOS_LoginTwoFactorAuth
bin/magento setup:upgrade
bin/magento config:set customer_2fa/general/enabled 1
```

On Hyvä, regenerate the Tailwind config so the module templates are picked up:
`bin/magento hyva:config:generate` and rebuild the theme CSS. The templates also ship a small scoped stylesheet,
so they render correctly before the rebuild.

## Features

- **Opt-in per customer** from *My Account → Two-Factor Authentication*: enable, disable, regenerate recovery codes.
- QR code rendered server-side (SVG, `endroid/qr-code`): the secret never goes to a third-party service.
  Manual key and `otpauth://` link for setup on the same phone.
- **Recovery codes** (one-time, stored as SHA-256 hashes), with copy and download.
- Code challenge after a correct password on the standard login form, the Luma authentication popup
  (`customer/ajax/login`) and the Threecommerce OneStepCheckout inline login. After the code the customer lands
  where the login would have sent them (dashboard, checkout, "redirect after login" target).
- **Fail-closed**: the interception happens in `Magento\Customer\Model\Session::setCustomerDataAsLoggedIn()`, so any
  other login path (third-party modules, RSS feed basic auth…) cannot log in a 2FA customer without the code.
  Explicitly trusted: the signed store-switch redirect and admin *Login as Customer*.
- Replay protection (a code works once), clock tolerance, max attempts per sign-in; wrong codes also count towards
  the core customer lockout (*Customer Configuration → Password Options*).
- Turning 2FA off needs the password **and** a code. The secret is stored encrypted (`EncryptorInterface`).
- Admin: **Reset 2FA** button on the customer edit page (ACL `MageOS_LoginTwoFactorAuth::reset`).
- CLI: `bin/magento mageos:customer-2fa:reset <email> [--website=<id>]`.
- Hyvä templates; `en_US` and `it_IT` translations.

## Configuration

*Stores → Configuration → Customers → Two-Factor Authentication*

| Path | Default | |
|---|---|---|
| `customer_2fa/general/enabled` | 0 | Feature on/off (website scope) |
| `customer_2fa/general/issuer` | store name | Label shown in the authenticator app |
| `customer_2fa/general/leeway` | 1 | Accepted adjacent 30 s windows (0–2) |
| `customer_2fa/general/max_attempts` | 5 | Wrong codes before the password step must be redone |
| `customer_2fa/general/recovery_codes` | 10 | Number of recovery codes generated |

When the feature is disabled, customers who had enabled 2FA sign in with password only (their setup is kept).

## Storefront URLs

| URL | |
|---|---|
| `/customer-2fa/account` | Manage 2FA (logged-in customers) |
| `/customer-2fa/login` | Code challenge after the password step |

## Not covered

- GraphQL `generateCustomerToken` and REST customer tokens (headless storefronts) are not challenged.
- "Remember this device" and email notifications on enable/disable are not implemented.

## Data

Table `mageos_customer_tfa`: one row per customer with 2FA active, foreign key to `customer_entity` with
`ON DELETE CASCADE`.

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/mage-os/module-login-two-factor-auth/Test/Unit
```

## License

MIT, see [LICENSE.md](LICENSE.md).
