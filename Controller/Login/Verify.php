<?php

declare(strict_types=1);

namespace MageOS\LoginTwoFactorAuth\Controller\Login;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\AuthenticationInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use MageOS\LoginTwoFactorAuth\Model\Config;
use MageOS\LoginTwoFactorAuth\Model\LoginGuard;
use MageOS\LoginTwoFactorAuth\Model\TwoFactor;

class Verify implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly RedirectInterface $redirect,
        private readonly ManagerInterface $messageManager,
        private readonly Session $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AuthenticationInterface $authentication,
        private readonly AccountRedirect $accountRedirect,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoginGuard $loginGuard,
        private readonly TwoFactor $twoFactor,
        private readonly Config $config
    ) {
    }

    /**
     * A second submit of the challenge form (double click, autofill auto-submit) arrives after the
     * first one logged the customer in, and the login rotated the form key: send them to their
     * account silently instead of showing "Invalid Form Key".
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        if ($this->customerSession->isLoggedIn()) {
            return new InvalidRequestException($this->redirectFactory->create()->setPath('customer/account'), []);
        }

        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return null;
    }

    public function execute(): ResultInterface
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account');
        }

        $pending = $this->loginGuard->getPending();
        if ($pending === null) {
            $this->messageManager->addErrorMessage(__('Your sign-in session has expired. Please sign in again.'));
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        $customerId = (int) $pending['customer_id'];
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException) {
            // Account deleted while the challenge was pending.
            $this->loginGuard->clearPending();
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        if ($this->authentication->isLocked($customerId)) {
            $this->loginGuard->clearPending();
            $this->messageManager->addErrorMessage(__(
                'The account sign-in was incorrect or your account is disabled temporarily. '
                . 'Please wait and try again later.'
            ));
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        $code = trim((string) $this->request->getPost('code'));
        if ($code === '' || !$this->twoFactor->verifyTotpOrRecoveryCode($customerId, $code)) {
            // Counts towards the core account lockout too, so codes can't be brute-forced
            // across repeated password logins.
            $this->authentication->processAuthenticationFailure($customerId);
            if ($this->loginGuard->registerFailedAttempt() >= $this->config->getMaxAttempts()) {
                $this->loginGuard->clearPending();
                $this->messageManager->addErrorMessage(__('Too many invalid codes. Please sign in again.'));
                return $this->redirectFactory->create()->setPath('customer/account/login');
            }
            $this->messageManager->addErrorMessage(__('The authentication code is not valid.'));
            return $this->redirectFactory->create()->setPath('customer-2fa/login');
        }

        $this->loginGuard->clearPending();
        $this->authentication->unlock($customerId);
        $this->loginGuard->trusted(fn () => $this->customerSession->setCustomerDataAsLoggedIn($customer));
        $this->customerSession->regenerateId();

        if ($this->cookieManager->getCookie('mage-cache-sessid')) {
            $metadata = $this->cookieMetadataFactory->createCookieMetadata()->setPath('/');
            $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
        }

        if ($this->twoFactor->getRemainingRecoveryCodes($customerId) <= 2) {
            $this->messageManager->addWarningMessage(__(
                'You are running out of recovery codes. Generate new ones from My Account > Two-Factor Authentication.'
            ));
        }

        $target = $pending['redirect'] ?? null;
        if ($target && (!empty($pending['redirect_force'])
            || !$this->scopeConfig->getValue('customer/startup/redirect_dashboard'))
        ) {
            // success() only accepts internal URLs, falling back to the base URL otherwise.
            return $this->redirectFactory->create()->setUrl($this->redirect->success($target));
        }

        return $this->accountRedirect->getRedirect();
    }
}
