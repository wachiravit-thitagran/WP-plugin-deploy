# WP Plugin Deploy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a standalone WordPress plugin that registers deployment abilities into the WordPress Abilities API so ChatGPT can safely deploy, inspect, and roll back other WordPress plugins through the existing MCP Adapter.

**Architecture:** A thin abilities registration layer maps validated requests to focused services for package resolution, validation, backup, deployment, rollback, inspection, and metadata storage. All remote access and file manipulation use WordPress HTTP/filesystem/plugin APIs; no shell, SSH, Git CLI, or arbitrary target paths are allowed.

**Tech Stack:** PHP, WordPress Plugin API, WordPress Abilities API, WordPress HTTP API, WordPress Filesystem API, PHPUnit/WordPress test tooling where available.

**Spec:** `docs/superpowers/specs/2026-10-07-wp-plugin-deploy-design.md`

## Global Constraints

- Ship as one installable WordPress plugin.
- Register abilities directly into the WordPress Abilities API.
- Do not modify MCP Adapter code.
- Do not require shell access, `exec()`, SSH, Git CLI, or Composer on the target site.
- Support public GitHub repository/archive sources and direct HTTPS ZIP URLs in the first release.
- Restrict deployment targets to WordPress plugins under the configured plugins directory.
- Mutation abilities must enforce `install_plugins`, `update_plugins`, and `activate_plugins` as applicable.
- Reject ZIP Slip/path traversal, absolute archive paths, unsupported URL schemes, ambiguous plugin roots, and invalid plugin slugs.
- Back up an installed plugin before replacement and automatically roll back when a post-replacement deployment stage fails.
- Do not store authentication secrets in WordPress options.
- Deployment is successful only after WordPress plugin discovery confirms the plugin; activation requests also require active-state confirmation.

## Review Focus

- GitHub archives whose generated top-level directory differs from the desired plugin slug must still normalize to one unambiguous plugin root.
- ZIP archives containing nested `../`, absolute paths, or mixed safe/unsafe entries must be rejected before extraction into a deployable location.
- Packages containing more than one plausible plugin main file/root must fail with `ambiguous_plugin_root`, not choose arbitrarily.
- Update failure after target replacement begins must restore the exact backup and previous activation state, then report both original failure and rollback result.
- Direct HTTPS URLs that redirect to unsupported schemes or fail archive validation must be treated as invalid/download failures without writing to the plugins directory.

---

### Task 1: Plugin Bootstrap and Abilities Registration

**Files:**
- Create: `wp-plugin-deploy.php`
- Create: `includes/class-abilities.php`
- Create: `tests/test-abilities.php`
- Create: `README.md`

**Interfaces:**
- Consumes: WordPress action/filter hooks and the site Abilities API registration function available at runtime.
- Produces: `WP_Plugin_Deploy_Abilities::register(): void` and three registered ability names: `wordpress/plugin-deploy`, `wordpress/plugin-rollback`, `wordpress/plugin-deploy-status`.

- [ ] **Step 1: Write failing bootstrap/registration tests**
  - Assert the plugin defines its version/path constants.
  - Assert registration exposes exactly the three required ability names.
  - Assert mutation abilities are marked non-readonly and status is readonly.
  - Assert registration fails gracefully when the required Abilities API registration function/hook is unavailable.

- [ ] **Step 2: Run the focused tests and verify they fail**
  - Run the project test command targeting `tests/test-abilities.php`.
  - Expected: FAIL because bootstrap and registration classes do not exist.

- [ ] **Step 3: Implement plugin bootstrap and `WP_Plugin_Deploy_Abilities::register(): void`**
  - Keep callbacks thin and delegate business logic to services introduced by later tasks.
  - Define input/output schemas for all three abilities.
  - Add capability callback wiring but no deployment behavior yet.

- [ ] **Step 4: Run the focused tests and verify they pass**

- [ ] **Step 5: Commit**
  - Commit message: `feat: register plugin deployment abilities`

### Task 2: Source Resolution and URL Validation

**Files:**
- Create: `includes/class-package-resolver.php`
- Create: `tests/test-package-resolver.php`

**Interfaces:**
- Consumes: normalized deployment request array from the abilities layer.
- Produces: `WP_Plugin_Deploy_Package_Resolver::resolve(array $request): array|WP_Error` returning normalized `source_type`, `download_url`, `source_reference`, and selector metadata.

- [ ] **Step 1: Write failing resolver tests**
  - Public GitHub repository URL + branch resolves to a GitHub archive HTTPS URL.
  - Public GitHub repository URL + tag resolves deterministically.
  - Direct HTTPS ZIP URL passes through as a direct package source.
  - HTTP, file, ftp, malformed URLs, and unsupported GitHub URL shapes return `invalid_source`.
  - Redirect/final URL validation is represented so a non-HTTPS final target cannot become deployable.

- [ ] **Step 2: Run resolver tests and verify they fail**

- [ ] **Step 3: Implement `resolve(array $request): array|WP_Error`**
  - Accept explicit source mode where provided.
  - Normalize GitHub owner/repository and branch/tag/release selectors.
  - Do not add private-repository credential storage.

- [ ] **Step 4: Run resolver tests and verify they pass**

- [ ] **Step 5: Commit**
  - Commit message: `feat: resolve plugin deployment sources`

### Task 3: ZIP and Plugin Package Validation

**Files:**
- Create: `includes/class-package-validator.php`
- Create: `includes/class-plugin-inspector.php`
- Create: `tests/test-package-validator.php`
- Create: `tests/fixtures/` package fixtures as needed

**Interfaces:**
- Consumes: downloaded ZIP path and optional expected plugin slug.
- Produces: `WP_Plugin_Deploy_Package_Validator::validate(string $zip_path, ?string $expected_slug = null): array|WP_Error` returning normalized plugin root, main plugin relative path, detected slug, plugin headers, and safe file manifest.

- [ ] **Step 1: Write failing validator tests**
  - Valid single-plugin ZIP is accepted.
  - GitHub-generated top-level directory is normalized correctly.
  - `../` path traversal is rejected.
  - Absolute Unix/Windows-style archive paths are rejected.
  - Invalid plugin slug is rejected.
  - No plugin main file returns `invalid_plugin_package`.
  - Multiple plausible roots/main files return `ambiguous_plugin_root`.
  - Main plugin headers are parsed without evaluating plugin PHP.

- [ ] **Step 2: Run validator tests and verify they fail**

- [ ] **Step 3: Implement validator and inspector**
  - Inspect archive entries before copying into the WordPress plugins directory.
  - Parse plugin headers as text.
  - Never execute package PHP during validation.

- [ ] **Step 4: Run validator tests and verify they pass**

- [ ] **Step 5: Commit**
  - Commit message: `feat: validate plugin deployment packages`

### Task 4: Deployment Metadata and Backup Management

**Files:**
- Create: `includes/class-deployment-store.php`
- Create: `includes/class-backup-manager.php`
- Create: `tests/test-deployment-store.php`
- Create: `tests/test-backup-manager.php`

**Interfaces:**
- Produces: `WP_Plugin_Deploy_Deployment_Store::record(array $event): void`, `latest(string $slug): ?array`, `history(string $slug): array`.
- Produces: `WP_Plugin_Deploy_Backup_Manager::create(string $slug, string $plugin_dir, bool $was_active): array|WP_Error`, `list(string $slug): array`, `get(string $slug, ?string $backup_id = null): array|WP_Error`.

- [ ] **Step 1: Write failing metadata/backup tests**
  - Backup metadata records slug, version, activation state, timestamp, and backup identifier.
  - Store records install/update/rollback states without secrets.
  - Latest/history retrieval is slug-scoped.
  - Backup retention is bounded by a configurable default.
  - Invalid/missing backup selection returns structured errors.

- [ ] **Step 2: Run tests and verify they fail**

- [ ] **Step 3: Implement deployment store and backup manager**
  - Keep metadata compact in WordPress options.
  - Store backup files under a dedicated content subdirectory outside the live plugin directory.
  - Enforce canonical paths under the backup root.

- [ ] **Step 4: Run tests and verify they pass**

- [ ] **Step 5: Commit**
  - Commit message: `feat: add deployment backups and metadata`

### Task 5: Core Deployer with Automatic Rollback

**Files:**
- Create: `includes/class-deployer.php`
- Create: `tests/test-deployer.php`
- Modify: `includes/class-abilities.php`

**Interfaces:**
- Consumes: resolver, validator, backup manager, deployment store.
- Produces: `WP_Plugin_Deploy_Deployer::deploy(array $request): array|WP_Error`.

- [ ] **Step 1: Write failing deployer tests**
  - Fresh install reaches plugin discovery and reports success.
  - Existing plugin update creates backup before replacement.
  - Activation request requires `activate_plugins` and verifies active state.
  - Capability failure returns `permission_denied` before filesystem mutation.
  - Download/archive/filesystem/activation/verification failures map to structured error codes.
  - Failure after replacement begins triggers automatic rollback.
  - Successful automatic rollback response reports original deployment failure plus `rolled_back=true`.
  - Failed automatic rollback returns `rollback_failed` while preserving original failure context.
  - Final redirected URL/source that violates HTTPS constraints never reaches plugin replacement.

- [ ] **Step 2: Run deployer tests and verify they fail**

- [ ] **Step 3: Implement `deploy(array $request): array|WP_Error`**
  - Use WordPress HTTP APIs for remote download.
  - Use temporary paths for extraction/staging.
  - Use WordPress filesystem APIs for target writes.
  - Keep install/update state transitions explicit so rollback only occurs after replacement has begun.

- [ ] **Step 4: Wire `wordpress/plugin-deploy` callback to the deployer**

- [ ] **Step 5: Run deployer and abilities tests and verify they pass**

- [ ] **Step 6: Commit**
  - Commit message: `feat: deploy plugins with automatic rollback`

### Task 6: Manual Rollback Service and Ability

**Files:**
- Create: `includes/class-rollback-manager.php`
- Create: `tests/test-rollback-manager.php`
- Modify: `includes/class-abilities.php`

**Interfaces:**
- Produces: `WP_Plugin_Deploy_Rollback_Manager::rollback(string $slug, ?string $backup_id = null): array|WP_Error`.

- [ ] **Step 1: Write failing rollback tests**
  - Latest backup is selected when no ID is supplied.
  - Explicit backup ID restores that backup only.
  - Current plugin directory is replaced from the selected backup.
  - Previous activation state is restored where possible.
  - Missing/invalid backup returns a structured error.
  - Rollback event is recorded in deployment metadata.

- [ ] **Step 2: Run rollback tests and verify they fail**

- [ ] **Step 3: Implement rollback manager and wire `wordpress/plugin-rollback`**

- [ ] **Step 4: Run rollback tests and verify they pass**

- [ ] **Step 5: Commit**
  - Commit message: `feat: add manual plugin rollback ability`

### Task 7: Deployment Status Ability

**Files:**
- Modify: `includes/class-plugin-inspector.php`
- Modify: `includes/class-abilities.php`
- Create: `tests/test-deploy-status.php`

**Interfaces:**
- Produces: `WP_Plugin_Deploy_Plugin_Inspector::status(string $slug): array`.

- [ ] **Step 1: Write failing status tests**
  - Installed plugin returns plugin file, version, and active state.
  - Missing plugin returns `installed=false`.
  - Latest deployment metadata is included.
  - Available backup metadata is included without exposing filesystem internals unnecessarily.
  - Ability is readonly and does not require mutation capabilities.

- [ ] **Step 2: Run status tests and verify they fail**

- [ ] **Step 3: Implement status inspection and wire `wordpress/plugin-deploy-status`**

- [ ] **Step 4: Run status tests and verify they pass**

- [ ] **Step 5: Commit**
  - Commit message: `feat: expose plugin deployment status`

### Task 8: Uninstall Behavior, Documentation, and Release Verification

**Files:**
- Create: `uninstall.php`
- Modify: `README.md`
- Modify: test configuration files as required

**Interfaces:**
- Consumes all prior tasks.
- Produces a releasable WordPress plugin repository and documented installation/use flow.

- [ ] **Step 1: Write/extend tests for uninstall policy and full ability schemas**
  - Uninstall removes deployment metadata according to documented policy.
  - Backups are either retained or removed according to the documented default; behavior must be explicit.
  - Ability schemas match the implemented request/response fields.

- [ ] **Step 2: Run the complete automated test suite**

- [ ] **Step 3: Run PHP syntax validation for every PHP file**
  - Expected: no syntax errors.

- [ ] **Step 4: Update README**
  - Installation instructions.
  - Required WordPress/Abilities API prerequisites.
  - Ability names and parameters.
  - GitHub/ZIP examples.
  - Capability/security model.
  - Backup/rollback behavior.
  - Known first-release limitations.

- [ ] **Step 5: Verify repository package layout is installable as a WordPress plugin**

- [ ] **Step 6: Commit**
  - Commit message: `docs: prepare WP Plugin Deploy release`

### Task 9: End-to-End Connector Verification on BIA E-Learning

**Files:**
- No repository code changes unless verification exposes a defect.

**Interfaces:**
- Consumes the built plugin ZIP/repository and the existing BIA E-Learning WordPress connector.
- Produces proof that MCP discovery and ability execution work on the target site.

- [ ] **Step 1: Install and activate WP Plugin Deploy on BIA E-Learning using the available bootstrap installation path**

- [ ] **Step 2: Discover abilities through the existing MCP Adapter**
  - Expected: `wordpress/plugin-deploy`, `wordpress/plugin-rollback`, and `wordpress/plugin-deploy-status` are visible.

- [ ] **Step 3: Execute `wordpress/plugin-deploy-status` against a known plugin**
  - Expected: structured readonly response.

- [ ] **Step 4: Deploy one controlled test/custom plugin through `wordpress/plugin-deploy`**
  - Expected: install/update succeeds and is visible to WordPress.

- [ ] **Step 5: Verify rollback path using a controlled test update**
  - Expected: previous version can be restored and activation state remains correct.

- [ ] **Step 6: If defects are found, return to the owning task with a failing regression test before fixing**

- [ ] **Step 7: Final release verification**
  - Full test suite PASS.
  - PHP syntax validation PASS.
  - Connector discovery PASS.
  - Controlled deploy PASS.
  - Controlled rollback PASS.
