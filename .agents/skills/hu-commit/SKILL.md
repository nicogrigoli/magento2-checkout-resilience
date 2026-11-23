---
name: hu-commit
description: Analyzes staged changes, detects mixed concerns for atomic commits, classifies them using Conventional Commits in English (supporting Adobe Commerce/Magento 2 and tooling/skills), and executes the git commit.
---
# Conventional Commit Generator

This skill analyzes staged changes, ensures atomic commits by separating unrelated concerns, classifies them using the Conventional Commits standard, and executes the git commit.

## When to use
- When the user asks to "Make the commit", "Commit this", "Generate commit", or "Classify my changes".

## Execution steps

1. **Inspect Staged Files (Low-Token Check):**
   - Run `git branch --show-current` to extract the issue ID if present (e.g., `feature/issue-1-skeleton` -> `1`).
   - Run `git diff --staged --name-status` to inspect affected file paths before reading code.
   - If nothing is staged, notify the user to stage files first (`git add <files>`) and stop.

2. **Check for Mixed Concerns (Atomic Commit Gate):**
   - Group staged files by domain (e.g., *Magento module code* vs. *AI skills/agent configs* vs. *CI/docs*).
   - **If all files belong to a single logical concern:** Proceed to Step 3.
   - **If files mix unrelated concerns** (e.g., a Magento feature + an AI skill optimization):
     - **Do NOT commit everything together.**
     - Propose a split plan listing each suggested commit message and its corresponding files.
     - Ask the user for confirmation to unstage and commit each group sequentially (`git reset HEAD` -> `git add <group>` -> `git commit`). Stop and wait for approval.

3. **Read Minimal Diff:**
   - Run `git diff --staged -U1 -- . ':(exclude)*lock*'` to inspect only essential code changes with minimal context (saving tokens).

4. **Classify and Draft:**
   - **Type:** `feat`, `fix`, `chore`, `refactor`, `docs`, `test`, or `perf`.
   - **Scope:**
     - *Magento 2:* `setup`, `admin`, `api`, `graphql`, `db`, `ui`, `checkout`, `di`, or the specific module area.
     - *Tooling & Meta:* `skills` (AI agent skills/prompts), `ci`, `deps`, `docs`, `config`.
   - **Description:** Short, imperative sentence in **English** (max 72 chars total).
   - **Issue ID Rule:** Append `(#ID)` ONLY if the branch contains an issue ID **and** the changes actually belong to that issue. Omit `(#ID)` for unrelated tooling/skill tweaks.
   - *Format:* `type(scope): description` or `type(scope): description (#ID)`

5. **Execute Commit:**
   - Run `git commit -m "<final_message>"` in the terminal.
   - If executing a user-approved split from Step 2, unstage all (`git reset`), then stage and commit each group one by one.

6. **Notify:**
   - Confirm the commit(s) created, display the exact message(s) used, and remind the user they can run `git commit --amend` if they want to tweak the last message.