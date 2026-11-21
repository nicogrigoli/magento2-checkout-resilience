---
name: hu-dev-requirements
description: Automates Definition of Done (DoD) and quality verification checks for Magento 2 / Adobe Commerce changes before committing or opening a PR.
---

# Development Requirements Verification (DoD)

This skill guides the agent to audit local code changes against Adobe Commerce best practices, security, testing, performance, and documentation standards before finalizing a ticket or User Story (HU).

## When to use
- When the developer finishes local changes for an issue, ticket, or User Story (HU) and wants to validate quality before committing or creating a Pull Request.

## Execution steps

1. **Identify scope of changes**
   - Run `git status` and `git diff` (staged and unstaged) to identify all modified and newly created files.
   - Focus all subsequent checks strictly on the affected modules and files.

2. **Run PHP_CodeSniffer (Coding Standards)**
   - Execute `vendor/bin/phpcs --standard=Magento2 <changed_php_files>` (or the project's configured PHPCS command) on all modified PHP files.
   - Report any errors or warnings with file paths and line numbers.

3. **Verify ACL & Security**
   - Check if the changes introduce new Adminhtml controllers, menus, system configuration sections, or WebAPI endpoints.
   - If so, verify that `etc/acl.xml` defines the required permissions and that controllers/endpoints enforce them (`ADMIN_RESOURCE` / `<resource ref="..."/>`).
   - Verify no sensitive data (tokens, passwords, payment/CVV fields) is exposed in logs or frontend payloads.

4. **Verify Module Dependencies & Declarative Schema**
   - **`etc/module.xml` (`<sequence>`):** Inspect PHP imports (`use` statements), `di.xml`, `webapi.xml`, and layout/UI XMLs in the diff. Verify that every referenced Magento or third-party module is declared in `<sequence>` when load order or hard dependencies apply.
   - **`composer.json`:** Ensure all used modules, PHP extensions, and third-party packages are listed under `"require"` (and `"require-dev"` for test-only dependencies) with appropriate version constraints.
   - **Database Schema Whitelist:** If `etc/db_schema.xml` was created or modified, verify that `etc/db_schema_whitelist.json` exists and reflects the schema changes. If it is missing or out of sync, flag it and recommend running `bin/magento setup:db-declaration:generate-whitelist --module-name=<Vendor_Module>`.

5. **Check Automated Tests**
   - Evaluate whether the new logic requires Unit or Integration tests (e.g., new services, ViewModels, plugins, or bug fixes).
   - Run existing and newly added PHPUnit tests for the affected module(s) and confirm they pass without errors.

6. **Verify Adobe Commerce Best Practices**
   - **PHP & Architecture:** Check for `declare(strict_types=1);`, strict typing, SOLID principles, Service Contracts, and ViewModels over heavy Blocks.
   - **Dependency Injection:** Strictly reject direct `ObjectManager` usage; always enforce Constructor Dependency Injection (DI).
   - **Extensibility:** Prefer Plugins and `virtualTypes` over class preferences. When using Plugins, prioritize `before` or `after` plugins and only allow `around` plugins as a last resort (when modifying execution flow or wrapping the original call is strictly necessary).
   - **Data Access:** Avoid raw SQL queries; use Repositories, ResourceModels, or SearchCriteria instead.
   - **Core-First & Reusability:** Check if any newly created classes, helpers, or utilities duplicate functionality that already exists in `vendor/magento` (e.g., native Service Contracts, ViewModels, serializers, or validators) or installed third-party packages. Flag redundant custom code and suggest reusing the existing implementation.
   - **Frontend:** Ensure JavaScript is decoupled from `.phtml` templates using `data-mage-init` / RequireJS rather than inline `<script>` tags, and verify proper output escaping (`$escaper`).
   - **Module Decoupling (`app/code`):** Verify that custom modules do not tightly couple to other custom modules. Flag direct injections of concrete internal classes (`Model`, `ResourceModel`, `Helper`, `Block`) from another custom module—enforce communication through Service Contracts (`Api/` interfaces), Events/Observers, or Extension Attributes instead, and check for circular dependencies.

7. **Review Performance Impact**
   - Check for potential bottlenecks in the diff: N+1 database queries inside loops, loading full models/collections unnecessarily, heavy logic inside global Plugins or `ConfigProvider` classes, or cache-busting patterns (`cacheable="false"`).

8. **Check Documentation (`README.md`)**
   - Verify if the module's `README.md` (or project docs) needs updates to reflect new features, configuration paths, CLI commands, or architectural changes.

## Output Format
Present the final result as a structured checklist using:
- ✅ **Pass**
- ⚠️ **Warning / Recommendation**
- ❌ **Fail (Action Required)**

For any ⚠️ or ❌ item, include the exact file path, a brief explanation of the issue, and a concrete fix.