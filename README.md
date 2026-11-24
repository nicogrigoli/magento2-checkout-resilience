# Grigoli_CheckoutResilience

A payment-method-agnostic Adobe Commerce / Magento 2 module designed to improve checkout conversion rates by preventing session loss on failed transactions, enabling cart recovery, and orchestrating payment retries.

---

## Requirements

| Dependency | Version |
| :--- | :--- |
| **PHP** | `~8.2.0 \|\| ~8.3.0 \|\| ~8.4.0` |
| `magento/framework` | `103.0.*` |
| `magento/module-authorization` | `100.4.*` |
| `magento/module-backend` | `102.0.*` |
| `magento/module-checkout` | `100.4.*` |
| `magento/module-config` | `101.2.*` |
| `magento/module-quote` | `101.2.*` |
| `magento/module-sales` | `103.0.*` |
| `magento/module-store` | `101.1.*` |
| `psr/log` | `^3.0` |

---

## Features

### Implemented (Current Release)
* **Module Skeleton & Core Architecture:** Standardized Magento 2 module registration (`Grigoli_CheckoutResilience`), Composer package definition (`grigoli/module-checkout-resilience`), and strict module load sequencing (`Magento_Authorization`, `Magento_Backend`, `Magento_Checkout`, `Magento_Config`, `Magento_Quote`, `Magento_Sales`, `Magento_Store`).
* **Global Enable/Disable Toggle:** Store-scoped administrative switch allowing merchants to enable or disable Checkout Resilience capabilities per Store View, Website, or Default Config without code deployments.
* **Role-Based Access Control (ACL):** Dedicated ACL resource (`Grigoli_CheckoutResilience::checkout_resilience`) nested under the **Sales** permission tree to restrict configuration access to authorized administrators.
* **Typed Configuration Service (`Model\Config`):** Strictly typed service class using `ScopeConfigInterface` to read module settings with `ScopeInterface::SCOPE_STORE` support, including global enablement, failure page recovery button enablement, customizable button label resolution, post-restoration redirect destination/custom URL configuration, and pre-failure payment retry limit settings.
* **Frontend Checkout Configuration Injection (`Model\Checkout\ConfigProvider`):** Implements `Magento\Checkout\Model\ConfigProviderInterface` and registers into `Magento\Checkout\Model\CompositeConfigProvider` to expose `window.checkoutConfig.resilience.enabled` on the storefront checkout page.
* **Failure Page Quote Restoration Button (`Controller\Quote\Restore`, `ViewModel\FailurePageQuoteRestore`, `view/frontend/*`):** Injects a configurable quote restoration button (`action primary`) into the `checkout_onepage_failure` layout when both global and failure-page toggles are active and a restorable order/quote exists in the checkout session. Submits a CSRF-protected (`form_key`) `POST` request to `checkout_resilience/quote/restore`, restores the active quote in session via `Magento\Checkout\Model\Session::restoreQuote()` for both guest and logged-in customers, redirects to the configured post-restoration destination on success, and safely handles errors by logging details, notifying the customer via `MessageManager`, and redirecting to `checkout/cart`.
* **Configurable Post-Restoration Redirect Destination & Open Redirect Protection (`Model\Config\Source\RedirectDestination`, `Model\Config\Backend\CustomRedirectUrl`, `Model\Resolver\RedirectUrlResolver`):** Allows administrators to configure the post-restoration redirect target per Store View (`checkout/#payment`, `checkout/#shipping`, `checkout/cart`, or a `custom` path/URL). Includes conditional Admin visibility (`<depends>`), a backend config model that validates custom paths/URLs on save, and a dedicated `RedirectUrlResolver` service that prevents Open Redirect vulnerabilities by verifying same-origin hosts via `Magento\Framework\Url\HostChecker` and safely falling back to `checkout/#payment` with a logged warning when an invalid or empty custom URL is encountered.
* **Pre-Failure Payment Retry Interception & Counter Management (`Model\Retry\RetryCounter`, `Observer\InterceptCheckoutFailureObserver`, `Observer\ResetRetryCounterOnSuccessObserver`, `etc/frontend/events.xml`):** Intercepts `controller_action_predispatch_checkout_onepage_failure` before the failure page renders when pre-failure retries are enabled and `attempts < max_retry_attempts`. Automatically restores the quote in session (`Session::restoreQuote()`), increments a per-quote retry counter stored in `Magento\Checkout\Model\Session`, adds a configurable warning message with the remaining attempts (`%1`), sets `ActionInterface::FLAG_NO_DISPATCH`, and redirects the customer back to `checkout/#payment`. Gracefully falls back to `checkout/onepage/failure` when the retry limit is reached or if quote restoration fails, and automatically resets the session retry counter on `checkout_onepage_controller_success_action`.
* **Order Eligibility Validation (`Model\Validator\OrderEligibilityValidator`):** Centralized eligibility validator shared across `ViewModel\FailurePageQuoteRestore`, `Controller\Quote\Restore`, and `Model\QuoteRestoreManagement`. Prevents duplicate orders and double charges by denying quote restoration when the associated order is in `processing`, `complete`, or `closed` state or already has linked invoices (`$order->hasInvoices()`), while permitting restoration when no order was created yet (`last_quote_id` without an order) or when the order is in `canceled`, `pending_payment`, `pending`, or `new` state without invoices. Logs structured PSR-3 warnings with `order_id`, `state`, `has_invoices`, and `reason` whenever a restoration attempt is denied.
* **Quote Reactivation Service (`Model\QuoteRestoreManagement`):** Centralized service that validates module enablement (`Model\Config`), input parameters, quote ownership/state, and associated order eligibility (`OrderEligibilityValidator`), locates and reactivates inactive quotes for both guest users (via masked cart IDs) and logged-in customers (via their most recent order), resets `is_active = 1` and `reserved_order_id = null`, persists the quote via `CartRepositoryInterface`, dispatches the `resilience_restore_quote` event, and logs unexpected errors via `Psr\Log\LoggerInterface`.
* **Cart Recovery via REST WebAPI (`Api\*`, `Model\Data\QuoteRestoreResponse`, `Model\GuestQuoteRestore`, `Model\MineQuoteRestore`):** Service contracts with documented `@throws` annotations, Data Transfer Object (`QuoteRestoreResponseInterface`), and REST endpoints (`POST /V1/guest-carts/:cartId/resilience/restore` and `POST /V1/carts/mine/resilience/restore`) returning `{"success": true}` (`200 OK`) on success and throwing native Magento framework exceptions (`InputException`, `LocalizedException`, `AuthorizationException`, `NoSuchEntityException`, `\RuntimeException`) for standardized HTTP error responses (`400`, `401`, `404`, `500`).
* **Unit Test Coverage:** Comprehensive PHPUnit test suites covering `Model\Config`, `Model\Config\Source\RedirectDestination`, `Model\Config\Backend\CustomRedirectUrl`, `Model\Resolver\RedirectUrlResolver`, `Model\Retry\RetryCounter`, `Model\Validator\OrderEligibilityValidator`, `Observer\InterceptCheckoutFailureObserver`, `Observer\ResetRetryCounterOnSuccessObserver`, `Model\Checkout\ConfigProvider`, `Model\Data\QuoteRestoreResponse`, `Model\GuestQuoteRestore`, `Model\MineQuoteRestore`, `Model\QuoteRestoreManagement`, `ViewModel\FailurePageQuoteRestore`, and `Controller\Quote\Restore`.

### Planned Capabilities (Module Roadmap)
* **Automated Payment Retries:** Automatic retry execution triggered by specific, configurable gateway error codes/responses, bounded by `max_automatic_retries`.

---

## Configuration & Usage

### 1. Installation & Module Activation

Enable the module, update the database schema/module registry, and clear the configuration cache using the Magento CLI:

```bash
bin/magento module:enable Grigoli_CheckoutResilience
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean config full_page
```

### 2. Admin Panel Configuration

Store administrators with the **Sales > Checkout Resilience** ACL permission can configure the module from the Magento Admin Panel:

1. Log in to the **Magento Admin Panel**.
2. Navigate to **Stores > Settings > Configuration**.
3. In the left sidebar, expand **Sales** and select **Checkout Resilience**.
4. Expand the **Basic settings** section:
   * **Enabled**: Select **Yes** to activate Checkout Resilience on the storefront checkout, or **No** to disable it (Default: `No`).
5. Expand the **Failure Page Recovery** section:
   * **Enable Failure Page Button**: Select **Yes** to display the quote restoration button on `checkout/onepage/failure` when the module is enabled (Default: `No`).
   * **Button Label**: Enter a custom display label for the restoration button (Default: `Retry Payment`).
   * **Redirect Destination**: Select where customers are redirected after restoring their quote (`Checkout Payment Step`, `Checkout Shipping Step`, `Shopping Cart`, or `Custom URL`; Default: `Checkout Payment Step`).
   * **Custom Redirect Path / URL**: *(Visible only when **Redirect Destination** is set to **Custom URL**)* Enter an internal relative path (e.g., `checkout/cart` or `custom-recovery-page`) or a full URL matching the store's domain. External or malformed URLs are rejected on save and safely fall back to `checkout/#payment` at runtime.
   * **Enable Pre-Failure Retries**: Select **Yes** to intercept failed payment redirects before rendering `checkout/onepage/failure`, automatically restore the quote, and send the customer back to `checkout/#payment` while retry attempts remain (Default: `No`).
   * **Max Retry Attempts**: *(Visible when **Enable Pre-Failure Retries** is **Yes**)* Enter a non-negative integer (`0` or greater) specifying how many times a failed payment is intercepted before showing the failure page (Default: `3`).
   * **Retry Notice Message**: *(Visible when **Enable Pre-Failure Retries** is **Yes**)* Customize the notice message displayed on the payment step after an intercepted failure, using `%1` as the placeholder for remaining attempts (Default: `Payment failed. Please try again (%1 attempts remaining).`).
6. *(Optional)* Switch the **Scope** selector in the upper-left corner to configure the settings per **Website** or **Store View**.
7. Click **Save Config** and flush the configuration cache when prompted.

#### Configuration Reference

| Admin Path | XML Config Path | Scope | Type | Default | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `Sales > Checkout Resilience > Basic settings > Enabled` | `checkout_resilience/basic_settings/enable` | Default, Website, Store View | Yes/No (`boolean`) | `0` (`No`) | Master kill-switch for the `Grigoli_CheckoutResilience` module on the checkout page. |
| `Sales > Checkout Resilience > Failure Page Recovery > Enable Failure Page Button` | `checkout_resilience/failure_page_recovery/enable` | Default, Website, Store View | Yes/No (`boolean`) | `0` (`No`) | Controls whether the quote restoration button is rendered on `checkout_onepage_failure` (requires `basic_settings/enable` to also be `1`). |
| `Sales > Checkout Resilience > Failure Page Recovery > Button Label` | `checkout_resilience/failure_page_recovery/button_label` | Default, Website, Store View | Text (`string`) | `Retry Payment` | Display label for the failure page quote restoration button. Falls back to `Retry Payment` if left blank. |
| `Sales > Checkout Resilience > Failure Page Recovery > Redirect Destination` | `checkout_resilience/failure_page_recovery/redirect_destination` | Default, Website, Store View | Select (`string`) | `checkout/#payment` | Destination route after a successful quote restoration from the failure page (`checkout/#payment`, `checkout/#shipping`, `checkout/cart`, or `custom`). |
| `Sales > Checkout Resilience > Failure Page Recovery > Custom Redirect Path / URL` | `checkout_resilience/failure_page_recovery/custom_redirect_url` | Default, Website, Store View | Text (`string`) | `""` (empty) | Custom internal relative path or same-origin store URL used when `redirect_destination` is set to `custom`. Validated against Open Redirect vulnerabilities. |
| `Sales > Checkout Resilience > Failure Page Recovery > Enable Pre-Failure Retries` | `checkout_resilience/failure_page_recovery/enable_pre_failure_retries` | Default, Website, Store View | Yes/No (`boolean`) | `0` (`No`) | Controls whether `checkout_onepage_failure` predispatch is intercepted to automatically restore the quote and redirect back to `checkout/#payment` while attempts remain (requires `basic_settings/enable` to also be `1`). |
| `Sales > Checkout Resilience > Failure Page Recovery > Max Retry Attempts` | `checkout_resilience/failure_page_recovery/max_retry_attempts` | Default, Website, Store View | Text / Integer (`int`) | `3` | Maximum number of pre-failure payment retry attempts per quote before displaying `checkout/onepage/failure` (`0` disables interception). |
| `Sales > Checkout Resilience > Failure Page Recovery > Retry Notice Message` | `checkout_resilience/failure_page_recovery/retry_notice_message` | Default, Website, Store View | Text (`string`) | `Payment failed. Please try again (%1 attempts remaining).` | Message template displayed on `checkout/#payment` after an intercepted failure (`%1` is replaced with remaining attempts). |

#### ACL Resource Reference

* **Resource ID:** `Grigoli_CheckoutResilience::checkout_resilience`
* **Admin Role Path:** `System > Permissions > User Roles > [Role] > Role Resources > Sales > Checkout Resilience`

### 3. CLI Configuration Management

Administrators and DevOps pipelines can read or toggle the module status directly via the Magento CLI:

```bash
# Enable the module globally (Default Config scope)
bin/magento config:set checkout_resilience/basic_settings/enable 1

# Enable the failure page quote restoration button, customize its label, and configure redirect destination
bin/magento config:set checkout_resilience/failure_page_recovery/enable 1
bin/magento config:set checkout_resilience/failure_page_recovery/button_label "Retry Payment"
bin/magento config:set checkout_resilience/failure_page_recovery/redirect_destination "checkout/#payment"

# Or configure a custom internal redirect path for a specific Store View
bin/magento config:set --scope=stores --scope-code=default checkout_resilience/failure_page_recovery/redirect_destination "custom"
bin/magento config:set --scope=stores --scope-code=default checkout_resilience/failure_page_recovery/custom_redirect_url "custom-recovery-page"

# Enable pre-failure automatic payment retry interception and configure max attempts
bin/magento config:set checkout_resilience/failure_page_recovery/enable_pre_failure_retries 1
bin/magento config:set checkout_resilience/failure_page_recovery/max_retry_attempts 3
bin/magento config:set checkout_resilience/failure_page_recovery/retry_notice_message "Payment failed. Please try again (%1 attempts remaining)."

# Enable the module for a specific Store View (e.g., "default")
bin/magento config:set --scope=stores --scope-code=default checkout_resilience/basic_settings/enable 1

# Disable the module globally
bin/magento config:set checkout_resilience/basic_settings/enable 0

# Check the current configuration value
bin/magento config:show checkout_resilience/basic_settings/enable

# Flush the configuration cache to apply changes
bin/magento cache:clean config
```

### 4. REST WebAPI Endpoints

The module exposes two REST endpoints to reactivate an inactive quote after a failed payment attempt:

| Method | Endpoint | Resource Access | Service Contract | Description |
| :--- | :--- | :--- | :--- | :--- |
| `POST` | `/V1/guest-carts/:cartId/resilience/restore` | `anonymous` | `Grigoli\CheckoutResilience\Api\GuestQuoteRestoreManagementInterface::execute` | Restores an inactive guest quote using its masked cart ID (`:cartId`). Returns `{"success": true}` (`200 OK`) on success or a standardized Magento WebAPI error response (`400`, `401`, `404`, `500`). |
| `POST` | `/V1/carts/mine/resilience/restore` | `self` | `Grigoli\CheckoutResilience\Api\MineQuoteRestoreManagementInterface::execute` | Restores the quote associated with the authenticated customer's most recent order. Returns `{"success": true}` (`200 OK`) on success or a standardized Magento WebAPI error response (`400`, `401`, `404`, `500`). |

#### HTTP Status Codes & Exception Mapping

| HTTP Status | Magento Exception | Scenario |
| :--- | :--- | :--- |
| `200 OK` | — | Quote successfully reactivated (`{"success": true}`). |
| `400 Bad Request` | `Magento\Framework\Exception\InputException` | Missing, empty, or malformed `:cartId` parameter. |
| `400 Bad Request` | `Magento\Framework\Exception\LocalizedException` | Business rule violation (e.g., module is disabled in configuration or quote is already active). |
| `401 Unauthorized` | `Magento\Framework\Exception\AuthorizationException` | Unauthenticated user or attempting to restore a quote owned by another customer. |
| `404 Not Found` | `Magento\Framework\Exception\NoSuchEntityException` | Masked cart ID, quote entity, or customer order does not exist. |
| `500 Internal Server Error` | `\RuntimeException` | Unexpected system/database failure (full details logged via `Psr\Log\LoggerInterface`; generic message returned). |

#### Example Requests

```bash
# Restore a guest cart by masked quote ID
curl -X POST "https://example.com/rest/V1/guest-carts/MASKED_CART_ID/resilience/restore" \
  -H "Content-Type: application/json"

# Restore the last quote for an authenticated customer
curl -X POST "https://example.com/rest/V1/carts/mine/resilience/restore" \
  -H "Authorization: Bearer <CUSTOMER_ACCESS_TOKEN>" \
  -H "Content-Type: application/json"
```

#### Example Responses

**Success (`200 OK`):**
```json
{"success": true}
```

**Error (`400 / 401 / 404 / 500`):**
```json
{
  "message": "No quote found for customer ID \"%customerId\".",
  "parameters": {
    "customerId": 10
  }
}
```

### 5. Developer Usage

#### Backend Configuration Check (PHP)
Inject `Grigoli\CheckoutResilience\Model\Config` via Dependency Injection to check whether the module is enabled for the active or specified store scope:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Service;

use Grigoli\CheckoutResilience\Model\Config;

class CustomCheckoutService
{
    public function __construct(
        private readonly Config $checkoutResilienceConfig
    ) {
    }

    public function execute(?string $storeCode = null): void
    {
        if (!$this->checkoutResilienceConfig->getEnabled($storeCode)) {
            return;
        }

        // Execute resilience logic...
    }
}
```

#### Backend Quote Restoration & Dispatched Events
When a quote is successfully restored through `Grigoli\CheckoutResilience\Model\QuoteRestoreManagement`, the module:
1. Sets `$quote->setIsActive(1)` and `$quote->setReservedOrderId(null)`.
2. Saves the quote via `Magento\Quote\Api\CartRepositoryInterface::save()`.
3. Dispatches the `resilience_restore_quote` event with `['quote' => $quote]` as event data, allowing third-party modules to hook into the restoration lifecycle.

#### Frontend (JavaScript / UI Components)
On the storefront checkout page, the configuration state is automatically exposed through the global `window.checkoutConfig` object:

```javascript
define([], function () {
    'use strict';

    return {
        isResilienceEnabled: function () {
            return Boolean(
                window.checkoutConfig &&
                window.checkoutConfig.resilience &&
                window.checkoutConfig.resilience.enabled
            );
        }
    };
});
```

### 6. Running Code Quality & Unit Tests

Execute Magento 2 PHP_CodeSniffer and the module's PHPUnit test suite from the Magento root directory:

```bash
# Run Magento 2 coding standards check
vendor/bin/phpcs --standard=Magento2 app/code/Grigoli/CheckoutResilience

# Run unit tests
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Grigoli/CheckoutResilience/Test/Unit
```

---

## Technical Architecture

```text
app/code/Grigoli/CheckoutResilience/
├── composer.json
├── registration.php
├── Api/
│   ├── GuestQuoteRestoreManagementInterface.php
│   ├── MineQuoteRestoreManagementInterface.php
│   └── Data/
│       └── QuoteRestoreResponseInterface.php
├── Controller/
│   └── Quote/
│       └── Restore.php
├── etc/
│   ├── acl.xml
│   ├── config.xml
│   ├── di.xml
│   ├── module.xml
│   ├── webapi.xml
│   ├── adminhtml/
│   │   └── system.xml
│   └── frontend/
│       ├── di.xml
│       ├── events.xml
│       └── routes.xml
├── Model/
│   ├── Config.php
│   ├── GuestQuoteRestore.php
│   ├── MineQuoteRestore.php
│   ├── QuoteRestoreManagement.php
│   ├── Checkout/
│   │   └── ConfigProvider.php
│   ├── Config/
│   │   ├── Backend/
│   │   │   └── CustomRedirectUrl.php
│   │   └── Source/
│   │       └── RedirectDestination.php
│   ├── Data/
│   │   └── QuoteRestoreResponse.php
│   ├── Resolver/
│   │   └── RedirectUrlResolver.php
│   ├── Retry/
│   │   └── RetryCounter.php
│   └── Validator/
│       └── OrderEligibilityValidator.php
├── Observer/
│   ├── InterceptCheckoutFailureObserver.php
│   └── ResetRetryCounterOnSuccessObserver.php
├── Test/
│   └── Unit/
│       ├── Controller/
│       │   └── Quote/
│       │       └── RestoreTest.php
│       ├── Model/
│       │   ├── ConfigTest.php
│       │   ├── GuestQuoteRestoreTest.php
│       │   ├── MineQuoteRestoreTest.php
│       │   ├── QuoteRestoreManagementTest.php
│       │   ├── Checkout/
│       │   │   └── ConfigProviderTest.php
│       │   ├── Config/
│       │   │   ├── Backend/
│       │   │   │   └── CustomRedirectUrlTest.php
│       │   │   └── Source/
│       │   │       └── RedirectDestinationTest.php
│       │   ├── Data/
│       │   │   └── QuoteRestoreResponseTest.php
│       │   ├── Resolver/
│       │   │   └── RedirectUrlResolverTest.php
│       │   ├── Retry/
│       │   │   └── RetryCounterTest.php
│       │   └── Validator/
│       │       └── OrderEligibilityValidatorTest.php
│       ├── Observer/
│       │   ├── InterceptCheckoutFailureObserverTest.php
│       │   └── ResetRetryCounterOnSuccessObserverTest.php
│       └── ViewModel/
│           └── FailurePageQuoteRestoreTest.php
├── ViewModel/
│   └── FailurePageQuoteRestore.php
└── view/
    └── frontend/
        ├── layout/
        │   └── checkout_onepage_failure.xml
        └── templates/
            └── onepage/
                └── failure/
                    └── restore.phtml
```

### Core Components

* **Module Registration & Metadata (`registration.php`, `etc/module.xml`, `composer.json`):**
  Registers the `Grigoli_CheckoutResilience` component with Magento's `ComponentRegistrar` and declares load order dependencies on core modules (`Magento_Authorization`, `Magento_Backend`, `Magento_Checkout`, `Magento_Config`, `Magento_Quote`, `Magento_Sales`, `Magento_Store`).
* **Admin System Configuration & Defaults (`etc/adminhtml/system.xml`, `etc/config.xml`, `Model/Config/Source/RedirectDestination.php`, `Model/Config/Backend/CustomRedirectUrl.php`):**
  Defines the `checkout_resilience` section under the `sales` tab with `basic_settings` (`enable`) and `failure_page_recovery` (`enable`, `button_label`, `redirect_destination`, `custom_redirect_url`) groups, backed by safe default values (`0`, `Retry Payment`, and `checkout/#payment`) in `etc/config.xml`, the `RedirectDestination` option source model, and the `CustomRedirectUrl` backend validator model.
* **Access Control List (`etc/acl.xml`):**
  Declares the `Grigoli_CheckoutResilience::checkout_resilience` ACL resource under `Magento_Sales::sales` to guard system configuration access.
* **Configuration Reader (`Model/Config.php`):**
  Encapsulates access to `Magento\Framework\App\Config\ScopeConfigInterface` for `checkout_resilience/basic_settings/enable`, `checkout_resilience/failure_page_recovery/enable`, `checkout_resilience/failure_page_recovery/button_label`, `checkout_resilience/failure_page_recovery/redirect_destination`, and `checkout_resilience/failure_page_recovery/custom_redirect_url` using `ScopeInterface::SCOPE_STORE`.
* **Failure Page Quote Restoration UI, Redirect Resolver & Controller (`etc/frontend/routes.xml`, `Controller/Quote/Restore.php`, `Model/Resolver/RedirectUrlResolver.php`, `ViewModel/FailurePageQuoteRestore.php`, `view/frontend/layout/checkout_onepage_failure.xml`, `view/frontend/templates/onepage/failure/restore.phtml`):**
  Renders a CSRF-protected `POST` form button on the checkout failure page via a dedicated `ViewModel` and `.phtml` template using `$escaper`, validates `form_key` and module enablement in `Controller\Quote\Restore`, restores the quote in session via `Magento\Checkout\Model\Session::restoreQuote()`, resolves the store-scoped destination URL via `RedirectUrlResolver` (preventing Open Redirects and falling back to `checkout/#payment` with a warning log when invalid), and redirects the customer (or redirects to `checkout/cart` with a user-friendly error message on restoration failure).
* **Pre-Failure Payment Retry Interception & Session Counter (`etc/frontend/events.xml`, `Model/Retry/RetryCounter.php`, `Observer/InterceptCheckoutFailureObserver.php`, `Observer/ResetRetryCounterOnSuccessObserver.php`):**
  Observes `controller_action_predispatch_checkout_onepage_failure` to intercept payment failure redirects while `attempts < max_retry_attempts`, restores the quote in session (`Session::restoreQuote()`), increments the quote-scoped retry counter in `RetryCounter`, adds a warning message with remaining attempts via `MessageManagerInterface`, sets `ActionInterface::FLAG_NO_DISPATCH`, and redirects to `checkout/#payment`. Resets the counter automatically on `checkout_onepage_controller_success_action`.
* **Checkout Config Provider & Frontend DI (`Model/Checkout/ConfigProvider.php`, `etc/frontend/di.xml`):**
  Implements `Magento\Checkout\Model\ConfigProviderInterface` and injects `checkout_resilience_config_provider` into `Magento\Checkout\Model\CompositeConfigProvider` (`configProviders` array argument). Exports `['resilience' => ['enabled' => bool]]` to the frontend checkout configuration payload.
* **Quote Restoration Service & Data Contracts (`Api/GuestQuoteRestoreManagementInterface.php`, `Api/MineQuoteRestoreManagementInterface.php`, `Api/Data/QuoteRestoreResponseInterface.php`, `Model/Data/QuoteRestoreResponse.php`, `etc/di.xml`, `etc/webapi.xml`):**
  Defines service contracts and the `QuoteRestoreResponseInterface` DTO for guest and authenticated quote restoration, binds them to their concrete implementations (`Model\GuestQuoteRestore`, `Model\MineQuoteRestore`, and `Model\Data\QuoteRestoreResponse`) in `etc/di.xml`, and exposes them as `POST` routes in `etc/webapi.xml`.
* **Quote Restoration Management (`Model/QuoteRestoreManagement.php`, `Model/GuestQuoteRestore.php`, `Model/MineQuoteRestore.php`):**
  Validates module activation (`Model\Config`), input parameters (`InputException`), and quote ownership (`AuthorizationException`), resolves guest quotes via `MaskedQuoteIdToQuoteIdInterface` and customer quotes via the latest order (`OrderRepositoryInterface` + `SearchCriteriaBuilder` + `SortOrderBuilder`) throwing `NoSuchEntityException` when missing, verifies the quote is inactive (`LocalizedException`), reactivates the quote (`setIsActive(1)`, `setReservedOrderId(null)`), saves it via `CartRepositoryInterface`, dispatches the `resilience_restore_quote` event, logs unexpected errors via `Psr\Log\LoggerInterface`, and wraps the outcome into a `QuoteRestoreResponseInterface` instance via `QuoteRestoreResponseInterfaceFactory`.
* **Unit Tests (`Test/Unit/*`):**
  PHPUnit test suites using data providers and mock objects to verify store-scoped configuration resolution (`ConfigTest`), checkout config serialization (`ConfigProviderTest`), response DTO behavior (`QuoteRestoreResponseTest`), WebAPI service delegation (`GuestQuoteRestoreTest`, `MineQuoteRestoreTest`), quote restoration management scenarios (`QuoteRestoreManagementTest`), failure page ViewModel logic (`FailurePageQuoteRestoreTest`), and POST controller flow (`RestoreTest`).
