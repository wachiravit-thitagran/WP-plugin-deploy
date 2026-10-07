# WP Plugin Deploy — Design Specification

Date: 2026-10-07

## 1. Objective

WP Plugin Deploy is a standalone WordPress plugin that registers deployment abilities directly into the WordPress Abilities API.

Its purpose is to bootstrap safe remote deployment of other WordPress plugins through the existing MCP Adapter. After WP Plugin Deploy is installed and activated once, ChatGPT can discover and execute the registered abilities without modifying the MCP Adapter.

Primary success criteria:

- Install this plugin once using the normal WordPress administration flow.
- The plugin registers deployment-related WordPress abilities automatically.
- The existing MCP Adapter discovers those abilities.
- A caller can install or update another plugin from a GitHub repository or ZIP package.
- Existing plugin files are backed up before replacement.
- Failed deployments can be rolled back.
- Deployment does not require shell access, Git CLI, SSH, or server-specific commands.
- Deployment operations remain limited to WordPress plugin management.

## 2. Scope

The first release provides these abilities:

### wordpress/plugin-deploy

Installs a new plugin or updates an existing plugin from a remote package.

Supported source types:

- GitHub repository archive
- GitHub release asset or release archive
- Direct HTTPS ZIP URL

Parameters will include:

- source URL or repository URL
- optional branch, tag, or release selector
- expected plugin slug when needed
- activate flag
- overwrite/update behavior

The ability will:

1. validate caller capability
2. resolve and download the package
3. validate the downloaded archive
4. identify the plugin root directory and main plugin file
5. create a backup when an installed version already exists
6. extract to a temporary directory
7. replace/install the plugin through WordPress filesystem APIs
8. optionally activate the plugin
9. validate the final installation state
10. record deployment metadata
11. automatically restore the backup when a failure occurs after replacement begins

### wordpress/plugin-rollback

Restores the most recent valid backup for a plugin.

Parameters:

- plugin slug
- optional backup identifier

The ability will validate the requested backup, replace the current plugin directory, restore activation state where possible, and record the rollback event.

### wordpress/plugin-deploy-status

Returns deployment information for a plugin.

Output includes:

- installed/not installed
- plugin file
- version
- active/inactive
- most recent deployment result
- source metadata when available
- available backup metadata

This ability is read-only.

## 3. Architecture

The plugin remains a single installable WordPress plugin, while internal responsibilities are separated into focused classes.

Proposed structure:

```
wp-plugin-deploy/
├── wp-plugin-deploy.php
├── includes/
│   ├── class-abilities.php
│   ├── class-deployer.php
│   ├── class-package-resolver.php
│   ├── class-package-validator.php
│   ├── class-backup-manager.php
│   ├── class-rollback-manager.php
│   ├── class-deployment-store.php
│   └── class-plugin-inspector.php
├── tests/
├── uninstall.php
└── README.md
```

### Bootstrap

`wp-plugin-deploy.php` will:

- define plugin constants
- load the internal classes
- initialize the deployment services
- register abilities at the appropriate WordPress Abilities API hook
- fail gracefully when the required Abilities API is unavailable

The plugin must not modify MCP Adapter code.

### Abilities layer

The abilities layer only handles:

- ability registration
- input/output schema
- capability validation
- normalization of parameters
- mapping service errors into structured responses

Deployment logic stays outside the ability callbacks.

### Deployment layer

The deployer orchestrates:

resolver -> download -> validation -> backup -> install/replace -> activation -> verification -> record result

Each subsystem remains independently testable.

## 4. WordPress Integration

The implementation will use WordPress-native APIs where practical:

- HTTP API for downloads
- filesystem API for file operations
- plugin API for plugin discovery and activation
- temporary files/directories managed through WordPress/PHP APIs
- WordPress options for compact deployment metadata

The implementation will not rely on:

- shell commands
- `exec()`
- Git CLI
- SSH
- Composer on the target site

This makes it suitable for shared hosting and environments where only WordPress filesystem access is available.

## 5. Package Resolution

### GitHub repository

The resolver will accept canonical GitHub repository URLs.

Selectors:

- branch
- tag
- release

If none is provided, the default branch or an appropriate latest release path may be used according to the explicit source mode.

GitHub package handling must tolerate the common archive behavior where the ZIP contains a generated root directory such as:

`repository-name-commit-or-tag/`

The deployer must normalize this into the actual WordPress plugin directory.

### Direct ZIP URL

Only HTTPS URLs are accepted by default.

The downloaded file must be successfully interpreted as a ZIP archive before extraction.

## 6. Package Validation

Before writing into `wp-content/plugins`, the package validator must ensure:

- the archive can be opened
- no archive entry escapes the extraction root
- no absolute paths are accepted
- path traversal such as `../` is rejected
- a plausible WordPress plugin main file exists
- plugin headers can be parsed
- the resolved plugin root is unambiguous
- the target slug is valid
- the final target remains inside the WordPress plugins directory

The deployment feature is intentionally limited to plugins. It must not expose arbitrary filesystem extraction or arbitrary file-copy operations.

## 7. Permissions and Security

Mutation abilities require appropriate WordPress capabilities.

Expected capability checks:

- install: `install_plugins`
- update/replace: `update_plugins`
- activation: `activate_plugins`
- rollback: `update_plugins`

The implementation must:

- reject unauthorized execution
- validate and sanitize all string inputs
- validate remote URLs
- reject unsupported URL schemes
- protect against ZIP Slip/path traversal
- never evaluate source code during package inspection
- never accept arbitrary shell commands
- never accept arbitrary target paths
- avoid storing authentication secrets in WordPress options

Private GitHub repository authentication is out of scope for the first release unless credentials are supplied by a future secure integration.

## 8. Backup and Rollback

Before replacing an installed plugin, the current plugin directory is copied to a dedicated backup area under WordPress content storage.

The backup system records:

- plugin slug
- backup identifier
- original plugin version
- activation state
- creation timestamp
- source deployment identifier when applicable

A failed update must trigger automatic rollback when:

- backup creation completed successfully
- replacement started
- final installation or activation validation fails

Backup retention in the first release will be conservative and bounded to avoid unlimited disk growth. The exact retention count will be implementation-configurable, with a sensible default.

The rollback ability can restore a known backup explicitly.

## 9. Deployment Metadata

A compact deployment history is stored in WordPress options.

Each record may include:

- plugin slug
- action: install/update/rollback
- status: success/failure/rolled_back
- source type
- source reference
- requested selector
- detected version
- timestamp
- backup identifier
- normalized error code when applicable

Secrets and downloaded source contents are not stored.

## 10. Error Handling

Abilities return structured failures rather than raw PHP errors.

Error categories include:

- permission_denied
- invalid_source
- download_failed
- invalid_archive
- invalid_plugin_package
- ambiguous_plugin_root
- backup_failed
- filesystem_failed
- activation_failed
- verification_failed
- rollback_failed
- abilities_api_unavailable

Where rollback succeeds after a failed deployment, the response must explicitly report both the deployment failure and successful restoration.

## 11. Verification After Deployment

A deployment is successful only after the plugin is visible through WordPress plugin discovery.

When activation was requested, success also requires WordPress to report the plugin as active.

The system must not claim deployment success merely because file extraction completed.

## 12. Compatibility

Target:

- modern supported WordPress versions
- PHP versions supported by the target WordPress release
- environments using direct filesystem access or WordPress filesystem abstraction

The plugin should fail safely when the WordPress Abilities API required for registration is unavailable.

## 13. Testing Strategy

The project will include tests for the highest-risk logic.

Required coverage:

- repository URL normalization
- ZIP root normalization
- traversal/path rejection
- plugin header detection
- slug validation
- deployment state transitions
- backup metadata
- rollback selection
- structured error mapping

Integration-oriented tests should cover:

- fresh plugin install
- update of an installed plugin
- activation after deployment
- failed update followed by successful rollback
- status reporting

Static syntax validation must be run for all PHP files before release.

## 14. First Release Boundaries

Included:

- one WordPress plugin
- direct registration into WP Abilities
- GitHub public repository/archive support
- direct HTTPS ZIP support
- install/update
- optional activation
- backup
- automatic rollback
- manual rollback
- deployment status

Not included initially:

- arbitrary server commands
- themes
- WordPress core updates
- Composer package deployment
- private GitHub credential storage
- arbitrary filesystem deployment
- multisite network orchestration beyond normal WordPress capability behavior
- user-facing admin dashboard

These exclusions keep the first release focused on the bootstrap goal: safely deploy other WordPress plugins through WP Abilities.

## 15. End-to-End Flow

Once released:

1. install and activate WP Plugin Deploy once
2. the plugin registers its WordPress abilities
3. MCP Adapter discovers the new abilities
4. ChatGPT invokes `wordpress/plugin-deploy`
5. WP Plugin Deploy downloads and validates the requested plugin
6. an existing installation is backed up when applicable
7. the new version is installed
8. activation and plugin discovery are verified
9. the deployment result is returned to ChatGPT
10. later plugin deployments can be performed entirely through the connector

No MCP Adapter modification is required.
