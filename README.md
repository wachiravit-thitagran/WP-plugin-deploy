# WP Plugin Deploy

Standalone WordPress plugin that registers deployment abilities into the WordPress Abilities API so MCP/AI clients can install, update, inspect, and roll back other plugins without modifying the MCP Adapter.

## Requirements

- WordPress 6.9+
- PHP 7.4+
- WordPress Abilities API available
- An authenticated user with the relevant plugin capabilities

## Registered abilities

- `wordpress/plugin-deploy`
- `wordpress/plugin-rollback`
- `wordpress/plugin-deploy-status`

The abilities are registered under the `plugin-deployment` category and exposed with `public` / `show_in_rest` metadata so compatible MCP adapters can discover them.

## Deploy input

`wordpress/plugin-deploy` accepts:

- `source` — required HTTPS URL. Either a public GitHub repository URL or direct HTTPS ZIP URL.
- `plugin_slug` — optional expected target slug.
- `ref` — optional Git branch/tag/ref for GitHub sources.
- `ref_type` — optional `branch`, `tag`, `release`, or empty string. The current release resolves Git refs through GitHub's public zipball endpoint.
- `activate` — optional boolean.

Example:

```json
{
  "source": "https://github.com/example/acme-plugin",
  "ref": "v1.2.0",
  "ref_type": "tag",
  "plugin_slug": "acme-plugin",
  "activate": true
}
```

## Safety model

- HTTPS-only remote sources.
- Public GitHub repository support; no persistent GitHub token storage.
- ZIP entries are inspected before extraction and path traversal / absolute paths are rejected.
- Deployment targets are restricted to plugin slugs below `WP_PLUGIN_DIR`.
- WordPress capability checks gate install, update, activation, and rollback operations.
- Existing plugin files are backed up before replacement.
- Failed post-replacement updates attempt automatic rollback.
- No shell commands, SSH, Git CLI, or arbitrary target paths.

## Backups

Backups are stored below `wp-content/wp-plugin-deploy-backups/`. The plugin keeps the latest three backups per plugin by default and stores compact metadata in WordPress options.

`wordpress/plugin-rollback` accepts a `plugin_slug` and optional `backup_id`. When no backup ID is supplied, the newest available backup is used.

## Status

`wordpress/plugin-deploy-status` returns installation state, plugin file/version, active state, latest deployment metadata, and backup identifiers without returning backup filesystem paths.

## Installation

Bootstrap installation still requires one ordinary WordPress plugin installation path, for example uploading the plugin ZIP in WordPress Admin. After activation, the existing MCP Adapter can discover the registered abilities and future custom plugin deployment can happen through those abilities.

## Development checks

```bash
php tests/smoke.php
php tests/abilities-smoke.php
php tests/rollback-smoke.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## First-release limits

- Public GitHub repositories and direct HTTPS ZIPs only.
- Plugins only; no theme or WordPress core deployment.
- No private repository credential persistence.
- No multisite network orchestration beyond the normal capabilities of the current WordPress user.
