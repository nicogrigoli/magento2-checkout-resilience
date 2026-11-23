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
* **Typed Configuration Service (`Model\Config`):** Strictly typed service class using `ScopeConfigInterface` to read module settings with `ScopeInterface::SCOPE_STORE` support, including global enablement, failure page recovery button enablement, and customizable button label resolution.
* **Frontend Checkout Configuration Injection (`Model\Checkout\ConfigProvider`):** Implements `Magento\Checkout\Model\ConfigProviderInterface` and registers into `Magento\Checkout\Model\CompositeConfigProvider` to expose `window.checkoutConfig.resilience.enabled` on the storefront checkout page.
* **Failure Page Quote Restoration Button (`Controller\Quote\Restore`, `ViewModel\FailurePageQuoteRestore`, `view/frontend/*`):** Injects a configurable quote restoration button (`action primary`) into the `checkout_onepage_failure` layout when both global and failure-page toggles are active and a restorable order/quote exists in the checkout session. Submits a CSRF-protected (`form_key`) `POST` request to `checkout_resilience/quote/restore`, restores the active quote in session via `Magento\Checkout\Model\Session::restoreQuote()` for both guest and logged-in customers, redirects to `checkout/#payment` on success, and safely handles errors by logging details, notifying the customer via `MessageManager`, and redirecting to `checkout/cart`.
* **Quote Reactivation Service (`Model\QuoteRestoreManagement`):** Centralized service that validates module enablement (`Model\Config`), input parameters, and quote ownership/state, locates and reactivates inactive quotes for both guest users (via masked cart IDs) and logged-in customers (via their most recent order), resets `is_active = 1` and `reserved_order_id = null`, persists the quote via `CartRepositoryInterface`, dispatches the `resilience_restore_quote` event, and logs unexpected errors via `Psr\Log\LoggerInterface`.
* **Cart Recovery via REST WebAPI (`Api\*`, `Model\Data\QuoteRestoreResponse`, `Model\GuestQuoteRestore`, `Model\MineQuoteRestore`):** Service contracts with documented `@throws` annotations, Data Transfer Object (`QuoteRestoreResponseInterface`), and REST endpoints (`POST /V1/guest-carts/:cartId/resilience/restore` and `POST /V1/carts/mine/resilience/restore`) returning `{"success": true}` (`200 OK`) on success and throwing native Magento framework exceptions (`InputException`, `LocalizedException`, `AuthorizationException`, `NoSuchEntityException`, `\RuntimeException`) for standardized HTTP error responses (`400`, `401`, `404`, `500`).
* **Unit Test Coverage:** Comprehensive PHPUnit test suites covering `Model\Config`, `Model\Checkout\ConfigProvider`, `Model\Data\QuoteRestoreResponse`, `Model\GuestQuoteRestore`, `Model\MineQuoteRestore`, `Model\QuoteRestoreManagement`, `ViewModel\FailurePageQuoteRestore`, and `Controller\Quote\Restore`.

### Planned Capabilities (Module Roadmap)
* **Configurable Post-Restoration Redirect Destination:** Admin configuration allowing merchants to customize the redirect target after restoring a quote from the failure page (e.g., checkout payment step vs. cart or custom URL).
* **Manual Payment Retries:** Customer-initiated payment retry flow on failed transactions, governed by an admin-configurable maximum retry limit (`max_manual_retries`) and feature toggle.
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
6. *(Optional)* Switch the **Scope** selector in the upper-left corner to configure the settings per **Website** or **Store View**.
7. Click **Save Config** and flush the configuration cache when prompted.

#### Configuration Reference

| Admin Path | XML Config Path | Scope | Type | Default | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `Sales > Checkout Resilience > Basic settings > Enabled` | `checkout_resilience/basic_settings/enable` | Default, Website, Store View | Yes/No (`boolean`) | `0` (`No`) | Master kill-switch for the `Grigoli_CheckoutResilience` module on the checkout page. |
| `Sales > Checkout Resilience > Failure Page Recovery > Enable Failure Page Button` | `checkout_resilience/failure_page_recovery/enable` | Default, Website, Store View | Yes/No (`boolean`) | `0` (`No`) | Controls whether the quote restoration button is rendered on `checkout_onepage_failure` (requires `basic_settings/enable` to also be `1`). |
| `Sales > Checkout Resilience > Failure Page Recovery > Button Label` | `checkout_resilience/failure_page_recovery/button_label` | Default, Website, Store View | Text (`string`) | `Retry Payment` | Display label for the failure page quote restoration button. Falls back to `Retry Payment` if left blank. |

#### ACL Resource Reference

* **Resource ID:** `Grigoli_CheckoutResilience::checkout_resilience`
* **Admin Role Path:** `System > Permissions > User Roles > [Role] > Role Resources > Sales > Checkout Resilience`

### 3. CLI Configuration Management

Administrators and DevOps pipelines can read or toggle the module status directly via the Magento CLI:

```bash
# Enable the module globally (Default Config scope)
bin/magento config:set checkout_resilience/basic_settings/enable 1

# Enable the failure page quote restoration button and customize its label
bin/magento config:set checkout_resilience/failure_page_recovery/enable 1
bin/magento config:set checkout_resilience/failure_page_recovery/button_label "Retry Payment"

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
│       └── routes.xml
├── Model/
│   ├── Config.php
│   ├── GuestQuoteRestore.php
│   ├── MineQuoteRestore.php
│   ├── QuoteRestoreManagement.php
│   ├── Checkout/
│   │   └── ConfigProvider.php
│   └── Data/
│       └── QuoteRestoreResponse.php
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
│       │   └── Data/
│       │       └── QuoteRestoreResponseTest.php
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
* **Admin System Configuration & Defaults (`etc/adminhtml/system.xml`, `etc/config.xml`):**
  Defines the `checkout_resilience` section under the `sales` tab with `basic_settings` (`enable`) and `failure_page_recovery` (`enable`, `button_label`) groups, backed by safe default values (`0` and `Retry Payment`) in `etc/config.xml`.
* **Access Control List (`etc/acl.xml`):**
  Declares the `Grigoli_CheckoutResilience::checkout_resilience` ACL resource under `Magento_Sales::sales` to guard system configuration access.
* **Configuration Reader (`Model/Config.php`):**
  Encapsulates access to `Magento\Framework\App\Config\ScopeConfigInterface` for `checkout_resilience/basic_settings/enable`, `checkout_resilience/failure_page_recovery/enable`, and `checkout_resilience/failure_page_recovery/button_label` using `ScopeInterface::SCOPE_STORE`.
* **Failure Page Quote Restoration UI & Controller (`etc/frontend/routes.xml`, `Controller/Quote/Restore.php`, `ViewModel/FailurePageQuoteRestore.php`, `view/frontend/layout/checkout_onepage_failure.xml`, `view/frontend/templates/onepage/failure/restore.phtml`):**
  Renders a CSRF-protected `POST` form button on the checkout failure page via a dedicated `ViewModel` and `.phtml` template using `$escaper`, validates `form_key` and module enablement in `Controller\Quote\Restore`, restores the quote in session via `Magento\Checkout\Model\Session::restoreQuote()`, and redirects to `checkout/#payment` (or `checkout/cart` with a user-friendly error message on failure).
* **Checkout Config Provider & Frontend DI (`Model/Checkout/ConfigProvider.php`, `etc/frontend/di.xml`):**
  Implements `Magento\Checkout\Model\ConfigProviderInterface` and injects `checkout_resilience_config_provider` into `Magento\Checkout\Model\CompositeConfigProvider` (`configProviders` array argument). Exports `['resilience' => ['enabled' => bool]]` to the frontend checkout configuration payload.
* **Quote Restoration Service & Data Contracts (`Api/GuestQuoteRestoreManagementInterface.php`, `Api/MineQuoteRestoreManagementInterface.php`, `Api/Data/QuoteRestoreResponseInterface.php`, `Model/Data/QuoteRestoreResponse.php`, `etc/di.xml`, `etc/webapi.xml`):**
  Defines service contracts and the `QuoteRestoreResponseInterface` DTO for guest and authenticated quote restoration, binds them to their concrete implementations (`Model\GuestQuoteRestore`, `Model\MineQuoteRestore`, and `Model\Data\QuoteRestoreResponse`) in `etc/di.xml`, and exposes them as `POST` routes in `etc/webapi.xml`.
* **Quote Restoration Management (`Model/QuoteRestoreManagement.php`, `Model/GuestQuoteRestore.php`, `Model/MineQuoteRestore.php`):**
  Validates module activation (`Model\Config`), input parameters (`InputException`), and quote ownership (`AuthorizationException`), resolves guest quotes via `MaskedQuoteIdToQuoteIdInterface` and customer quotes via the latest order (`OrderRepositoryInterface` + `SearchCriteriaBuilder` + `SortOrderBuilder`) throwing `NoSuchEntityException` when missing, verifies the quote is inactive (`LocalizedException`), reactivates the quote (`setIsActive(1)`, `setReservedOrderId(null)`), saves it via `CartRepositoryInterface`, dispatches the `resilience_restore_quote` event, logs unexpected errors via `Psr\Log\LoggerInterface`, and wraps the outcome into a `QuoteRestoreResponseInterface` instance via `QuoteRestoreResponseInterfaceFactory`.
* **Unit Tests (`Test/Unit/*`):**
  PHPUnit test suites using data providers and mock objects to verify store-scoped configuration resolution (`ConfigTest`), checkout config serialization (`ConfigProviderTest`), response DTO behavior (`QuoteRestoreResponseTest`), WebAPI service delegation (`GuestQuoteRestoreTest`, `MineQuoteRestoreTest`), quote restoration management scenarios (`QuoteRestoreManagementTest`), failure page ViewModel logic (`FailurePageQuoteRestoreTest`), and POST controller flow (`RestoreTest`).
