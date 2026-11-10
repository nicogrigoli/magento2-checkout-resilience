---
name: hu-start
description: Automates Git branch creation by connecting to GitHub Projects to retrieve issue details. Use when the user starts working on a new user story or ticket.
---
# GitHub Projects Branch Creator

This skill assists the agent in automating Git branch creation by interacting with GitHub Projects to retrieve the exact issue ID and context.

## When to use
- When the user starts working on a new User Story (HU), issue, or ticket.

## Execution steps
1. **Retrieve Issue Details**: The agent connects to GitHub Projects (via the integrated terminal using GitHub CLI commands like `gh issue view <ID>` or `gh issue list`) to fetch the correct issue ID and its title/context.
2. **Generate Branch Name**: The agent formulates a precise branch name using the format `feature/issue-<ID>-<short-context>`, where `<short-context>` is a lowercase, hyphen-separated string derived from the issue title.
3. **Execute Git Commands**: The agent executes the following commands sequentially in the integrated terminal without asking the user to run them manually:
   - `git checkout main`
   - `git pull origin main`
   - `git checkout -b feature/issue-<ID>-<short-context>`
   - `git commit --allow-empty -m "Start work on #<ID>: <short-context>"`
   - `git push -u origin feature/issue-<ID>-<short-context>`
   - `gh pr create --draft --title "WIP: <short-context>" --body "Closes #<ID>"`
4. **Notify the User**: The agent confirms the successful creation of the branch and reminds the user to include `Closes #<ID>` in their final commit message to update the GitHub Projects board automatically.