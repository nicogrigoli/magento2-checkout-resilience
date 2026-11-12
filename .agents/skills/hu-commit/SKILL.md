---
name: hu-commit
description: Classifies staged changes within an Adobe Commerce (Magento 2) context, drafts the commit message using the Conventional Commits standard in English, and automatically executes the git commit.
---
# Conventional Commit Generator

This skill analyzes locally staged code changes in an Adobe Commerce / Magento 2 project, classifies them according to the Conventional Commits standard, and automatically executes the commit.

## When to use
- When the user asks to "Make the commit", "Commit this", "Generate commit", or "Classify my changes".

## Execution steps
1. **Get branch context:** Execute `git branch --show-current` in the terminal. Extract the issue ID from the branch name (e.g., if the branch is `feature/issue-1-skeleton`, extract `1`).
2. **Read changes:** Execute `git diff --staged` in the terminal. If there are no changes in the staging area, notify the user that they must add files first (e.g., using `git add .`).
3. **Classify and Draft:** Based on the staged changes and the **Adobe Commerce (Magento 2) architecture**, define the following elements:
   - **Type:** `feat`, `fix`, `chore`, `refactor`, `docs`, or `test`.
   - **Scope:** The specific module, component, or area affected within the Magento ecosystem. Examples: `setup` (for initial scaffolding), `admin` (backend UI/Config/system.xml), `api` (Service Contracts/REST), `graphql` (GraphQL schema/resolvers), `db` (InstallSchema/Declarative Schema), `ui` (UI Components/Frontend layouts), `checkout` (Checkout adjustments/JS components), `di` (Dependency Injection configs/plugins/observers).
   - **Description:** A short, imperative sentence in **English** describing the action (e.g., "add global enable toggle").
   - *Final format:* `type(scope): description (#ID)`
4. **Execute Commit:** Automatically run the following command in the integrated terminal:
   `git commit -m "type(scope): description (#ID)"`
5. **Notify:** Confirm to the user that the commit was created successfully, display the exact commit message used, and remind them that they can use `git commit --amend` to adjust the text if necessary.