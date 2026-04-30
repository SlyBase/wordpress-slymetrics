---
applyTo: '**'
description: 'Completion workflow for Autopilot work: local tests must pass, then create a normal commit via the git-commit skill, run Build and Deploy Plugin ZIP, and perform an MCP test.'
---

# Autopilot Completion Workflow

This instruction applies whenever a cohesive Autopilot task in the repository is considered complete.

## 1) Local validation is mandatory

- Before any completion claim, the narrowest local tests or validation steps for the changed work must pass without errors.
- If focused PHPUnit tests exist for the change, run those first. Otherwise run the smallest meaningful local check for the affected area.
- If any test or check fails, the work is not complete.

## 2) Create a normal commit after successful validation

- After successful local validation, exactly one commit must be created for each completed task.
- Use the `git-commit` skill to analyze the diff and create a conventional commit message that matches the actual change.
- Do not force a beta suffix or version-based commit title.
- Before creating the commit, run `WP Plugin: Prepare Release Files` (or `.github/scripts/build-plugin-zip.sh`) once so generated language files are refreshed.
- If that refresh updates tracked `.mo` files, stage them and include them in the same commit.
- Do not create the commit while any tracked generated language file changes are still unstaged or uncommitted.

## 3) Build and deploy after the commit

- Immediately after the commit, the VS Code task `WP Plugin: Build and Deploy Plugin ZIP` must complete successfully.
- A task is not finished unless the build succeeds and the plugin ZIP is uploaded successfully to WordPress.

## 4) WordPress MCP smoke test is mandatory

- After a successful deploy, run a smoke test via the `wordpress-slybase` MCP adapter to confirm the metric abilities are accessible.
- First call `wordpress-slybase-mcp-adapter-discover-abilities` and verify that the five `metrics/*` abilities (`metrics/get-summary`, `metrics/get-users`, `metrics/get-posts`, `metrics/get-plugins`, `metrics/get-site-health`) are listed.
- Then call `wordpress-slybase-mcp-adapter-execute-ability` with `ability_name: "metrics/get-summary"` and verify the response contains the keys `site`, `users`, `posts`, `plugins`, `wordpress_version`, and `php_version`.
- Until this smoke test succeeds, the work must not be reported as complete.

## 5) Failure handling

- If any step above fails, do not claim completion.
- Instead, report the failed step, the relevant error signal, and the next sensible repair step concisely.