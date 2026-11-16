# Grigoli_CheckoutResilience

A payment-method-agnostic Adobe Commerce / Magento 2 module designed to improve checkout conversion rates by preventing session loss on failed transactions, enabling cart recovery, and orchestrating payment retries.

---

## Requirements

| Dependency | Version |
| :--- | :--- |
| **PHP** | `~8.2.0 \|\| ~8.3.0 \|\| ~8.4.0` |
| `magento/framework` | `103.0.*` |
| `magento/module-backend` | `102.0.*` |
| `magento/module-checkout` | `100.4.*` |
| `magento/module-config` | `101.2.*` |
| `magento/module-sales` | `103.0.*` |
| `magento/module-store` | `101.1.*` |

---

## Features

### Implemented (Current Release)
* **Module Skeleton & Core Architecture:** Standardized Magento 2 module registration (`Grigoli_CheckoutResilience`), Composer package definition (`grigoli/module-checkout-resilience`), and strict module load sequencing (`Magento_Backend`, `Magento_Checkout`, `Magento_Config`, `Magento_Sales`, `Magento_Store`).
* **Global Enable/Disable Toggle:** Store-scoped administrative switch allowing merchants to enable or disable Checkout Resilience capabilities per Store View, Website, or Default Config without code deployments.
* **Role-Based Access Control (ACL):** Dedicated ACL resource (`Grigoli_CheckoutResilience::checkout_resilience`) nested under the **Sales** permission tree to restrict configuration access to authorized administrators.
* **Typed Configuration Service (`Model\Config`):** Strictly typed service class using `ScopeConfigInterface` to read module settings with `ScopeInterface::SCOPE_STORE` support.
* **Frontend Checkout Configuration Injection (`Model\Checkout\ConfigProvider`):** Implements `Magento\Checkout\Model\ConfigProviderInterface` and registers into `Magento\Checkout\Model\CompositeConfigProvider` to expose `window.checkoutConfig.resilience.enabled` on the storefront checkout page.
* **Quote Reactivation Service (`Model\QuoteRestoreManagement`):** Centralized service that locates and reactivates inactive quotes for both guest users (via masked cart IDs) and logged-in customers (via their most recent order), resets `is_active = 1` and `reserved_order_id = null`, persists the quote via `CartRepositoryInterface`, and dispatches the `resilience_restore_quote` event.
* **Cart Recovery via REST WebAPI (`Api\*`, `Model\Data\QuoteRestoreResponse`, `Model\GuestQuoteRestore`, `Model\MineQuoteRestore`):** Service contracts, Data Transfer Object (`QuoteRestoreResponseInterface`), and REST endpoints (`POST /V1/guest-carts/:cartId/resilience/restore` and `POST /V1/carts/mine/resilience/restore`) returning structured JSON responses (`{"success": true}` / `{"success": false}`) to enable headless storefronts and checkout UI components to restore quotes seamlessly.
* **Unit Test Coverage:** Comprehensive PHPUnit test suites covering `Model\Config`, `Model\Checkout\ConfigProvider`, `Model\Data\QuoteRestoreResponse`, `Model\GuestQuoteRestore`, `Model\MineQuoteRestore`, and `Model\QuoteRestoreManagement`.

### Planned Capabilities (Module Roadmap)
* **Checkout Session & Cart Recovery:** Actionable recovery on the `checkout_onepage_failure` page allowing customers to restore their active quote and either return directly to the checkout payment step or redirect to a configurable URL.
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
5. *(Optional)* Switch the **Scope** selector in the upper-left corner to configure the setting per **Website** or **Store View**.
6. Click **Save Config** and flush the configuration cache when prompted.

#### Configuration Reference

| Admin Path | XML Config Path | Scope | Type | Default | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `Sales > Checkout Resilience > Basic settings > Enabled` | `checkout_resilience/basic_settings/enable` | Default, Website, Store View | Yes/No (`boolean`) | `0` (`No`) | Master kill-switch for the `Grigoli_CheckoutResilience` module on the checkout page. |

#### ACL Resource Reference

* **Resource ID:** `Grigoli_CheckoutResilience::checkout_resilience`
* **Admin Role Path:** `System > Permissions > User Roles > [Role] > Role Resources > Sales > Checkout Resilience`

### 3. CLI Configuration Management

Administrators and DevOps pipelines can read or toggle the module status directly via the Magento CLI:

```bash
# Enable the module globally (Default Config scope)
bin/magento config:set checkout_resilience/basic_settings/enable 1

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
| `POST` | `/V1/guest-carts/:cartId/resilience/restore` | `anonymous` | `Grigoli\CheckoutResilience\Api\GuestQuoteRestoreManagementInterface::execute` | Restores an inactive guest quote using its masked cart ID (`:cartId`). Returns `{"success": true}` on success, `{"success": false}` otherwise. |
| `POST` | `/V1/carts/mine/resilience/restore` | `self` | `Grigoli\CheckoutResilience\Api\MineQuoteRestoreManagementInterface::execute` | Restores the quote associated with the authenticated customer's most recent order. Returns `{"success": true}` on success, `{"success": false}` otherwise. |

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

```json
{"success": true}
```

```json
{"success": false}
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
├── etc/
│   ├── acl.xml
│   ├── config.xml
│   ├── di.xml
│   ├── module.xml
│   ├── webapi.xml
│   ├── adminhtml/
│   │   └── system.xml
│   └── frontend/
│       └── di.xml
├── Model/
│   ├── Config.php
│   ├── GuestQuoteRestore.php
│   ├── MineQuoteRestore.php
│   ├── QuoteRestoreManagement.php
│   ├── Checkout/
│   │   └── ConfigProvider.php
│   └── Data/
│       └── QuoteRestoreResponse.php
└── Test/
    └── Unit/
        └── Model/
            ├── ConfigTest.php
            ├── GuestQuoteRestoreTest.php
            ├── MineQuoteRestoreTest.php
            ├── QuoteRestoreManagementTest.php
            ├── Checkout/
            │   └── ConfigProviderTest.php
            └── Data/
                └── QuoteRestoreResponseTest.php
```

### Core Components

* **Module Registration & Metadata (`registration.php`, `etc/module.xml`, `composer.json`):**
  Registers the `Grigoli_CheckoutResilience` component with Magento's `ComponentRegistrar` and declares load order dependencies on core modules (`Magento_Backend`, `Magento_Checkout`, `Magento_Config`, `Magento_Sales`, `Magento_Store`).
* **Admin System Configuration & Defaults (`etc/adminhtml/system.xml`, `etc/config.xml`):**
  Defines the `checkout_resilience` section under the `sales` tab, the `basic_settings` group, and the `enable` Yes/No field, backed by a safe default value of `0` (`disabled`) in `etc/config.xml`.
* **Access Control List (`etc/acl.xml`):**
  Declares the `Grigoli_CheckoutResilience::checkout_resilience` ACL resource under `Magento_Sales::sales` to guard system configuration access.
* **Configuration Reader (`Model/Config.php`):**
  Encapsulates access to `Magento\Framework\App\Config\ScopeConfigInterface::isSetFlag()` for the `checkout_resilience/basic_settings/enable` path using `ScopeInterface::SCOPE_STORE`, returning a strict `bool`.
* **Checkout Config Provider & Frontend DI (`Model/Checkout/ConfigProvider.php`, `etc/frontend/di.xml`):**
  Implements `Magento\Checkout\Model\ConfigProviderInterface` and injects `checkout_resilience_config_provider` into `Magento\Checkout\Model\CompositeConfigProvider` (`configProviders` array argument). Exports `['resilience' => ['enabled' => bool]]` to the frontend checkout configuration payload.
* **Quote Restoration Service & Data Contracts (`Api/GuestQuoteRestoreManagementInterface.php`, `Api/MineQuoteRestoreManagementInterface.php`, `Api/Data/QuoteRestoreResponseInterface.php`, `Model/Data/QuoteRestoreResponse.php`, `etc/di.xml`, `etc/webapi.xml`):**
  Defines service contracts and the `QuoteRestoreResponseInterface` DTO for guest and authenticated quote restoration, binds them to their concrete implementations (`Model\GuestQuoteRestore`, `Model\MineQuoteRestore`, and `Model\Data\QuoteRestoreResponse`) in `etc/di.xml`, and exposes them as `POST` routes in `etc/webapi.xml`.
* **Quote Restoration Management (`Model/QuoteRestoreManagement.php`, `Model/GuestQuoteRestore.php`, `Model/MineQuoteRestore.php`):**
  Resolves guest quotes via `MaskedQuoteIdToQuoteIdInterface` and customer quotes via the latest order (`OrderRepositoryInterface` + `SearchCriteriaBuilder` + `SortOrderBuilder`), reactivates the quote (`setIsActive(1)`, `setReservedOrderId(null)`), saves it via `CartRepositoryInterface`, dispatches the `resilience_restore_quote` event, and wraps the outcome into a `QuoteRestoreResponseInterface` instance via `QuoteRestoreResponseInterfaceFactory`.
* **Unit Tests (`Test/Unit/Model/*`):**
  PHPUnit test suites using data providers and mock objects to verify store-scoped flag resolution (`ConfigTest`), checkout config serialization (`ConfigProviderTest`), response DTO behavior (`QuoteRestoreResponseTest`), WebAPI service delegation (`GuestQuoteRestoreTest`, `MineQuoteRestoreTest`), and all quote restoration scenarios and exception handling (`QuoteRestoreManagementTest`).
