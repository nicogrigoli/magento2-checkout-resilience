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
* **Unit Test Coverage:** Comprehensive PHPUnit test suites covering `Model\Config` and `Model\Checkout\ConfigProvider` across store scopes and state permutations.

### Planned Capabilities (Module Roadmap)
* **Checkout Session & Cart Recovery:** Actionable recovery on the `checkout_onepage_failure` page allowing customers to restore their active quote and either return directly to the checkout payment step or redirect to a configurable URL.
* **Cart Recovery by webapi:** Quote restoration from webapi.
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

### 4. Developer Usage

#### Backend (PHP)
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

### 5. Running Unit Tests

Execute the module's PHPUnit test suite from the Magento root directory:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Grigoli/CheckoutResilience/Test/Unit
```

---

## Technical Architecture

```text
app/code/Grigoli/CheckoutResilience/
├── composer.json
├── registration.php
├── etc/
│   ├── acl.xml
│   ├── config.xml
│   ├── module.xml
│   ├── adminhtml/
│   │   └── system.xml
│   └── frontend/
│       └── di.xml
├── Model/
│   ├── Config.php
│   └── Checkout/
│       └── ConfigProvider.php
└── Test/
    └── Unit/
        └── Model/
            ├── ConfigTest.php
            └── Checkout/
                └── ConfigProviderTest.php
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
* **Unit Tests (`Test/Unit/Model/ConfigTest.php`, `Test/Unit/Model/Checkout/ConfigProviderTest.php`):**
  PHPUnit tests using data providers and mock objects to verify store-scoped flag resolution in `Model\Config` and checkout array serialization in `Model\Checkout\ConfigProvider`.
