# WP Reliable Document Queue

An independent WordPress plugin proof of concept demonstrating reliable background document processing using Action Scheduler, Composer, and testable PHP services.

This repository was created as a standalone technical demonstration. It contains **no client code, confidential specifications, proprietary documents, or code from previous engagements**. All sample documents and events are fictional.

## What it demonstrates

- Unique event identifiers backed by database constraints to prevent duplicate document records.
- Queued rendering and delivery tasks using Action Scheduler, bundled as a production Composer dependency.
- Expiring processing claims and fencing tokens to prevent an outdated worker from completing another worker's claim.
- Recovery of interrupted rendering, including reuse and SHA256 verification of an existing file.
- Separate delivery states and prevention of automatic resending after an ambiguous transport handoff.
- Dependency injection through interfaces, namespaced PHP classes, and Composer PSR4 autoloading.
- Automated behavioral tests, WordPress integration checks, coding standards, and builds on Windows, macOS, and Linux.

## Requirements

- WordPress 6.2 or later
- PHP 8.1 or later
- MySQL or MariaDB
- Git and Composer for development and building the ZIP
- PHP ZIP extension for packaging

**WooCommerce is not required.** The installable ZIP includes the production Action Scheduler dependency.

## Development

Clone the repository and install dependencies:

```bash
git clone https://github.com/olatechpro/wp-reliable-document-queue.git
cd wp-reliable-document-queue
composer install
```

Run the behavioral tests and WordPress Coding Standards check:

```bash
composer test
composer lint
```

The lint command checks the plugin entry point and `src/`, not third party `vendor/` files or test fixtures. Use `composer update` only when intentionally upgrading dependencies; review and commit the resulting `composer.lock`.

## Build and verify the plugin ZIP

Run the same commands in Windows PowerShell, macOS Terminal, or a Linux shell:

```bash
php scripts/build-zip.php
php scripts/verify-zip.php
```

The ZIP is written to `dist/wp-reliable-document-queue-<version>.zip`, where the version comes from the plugin header. The build uses committed source files and `composer.lock`, installs only production dependencies in an isolated temporary directory, and excludes development tools and tests.

An optional `bash scripts/build-zip.sh` wrapper is also available.

GitHub Actions builds and verifies the ZIP on Windows, macOS, and Ubuntu, and runs PHP tests and WordPress integration smoke checks.

## Install and test

Use a disposable local or staging WordPress site.

1. Upload the generated ZIP under **Plugins > Add New Plugin > Upload Plugin** and activate it.
2. From WP CLI in the WordPress installation, queue the same fictional event twice:

```bash
wp eval "wrdq_enqueue_document('demo-001'); wrdq_enqueue_document('demo-001');"
wp action-scheduler run --batch-size=100
```

3. Inspect the `{prefix}wrdq_documents` and `{prefix}wrdq_deliveries` database tables. For `demo-001`, expect one document with status `ready` and one delivery record with status `accepted`.
4. Inspect the generated fictional text file and compare its SHA256 hash with the database.
5. Optionally activate WooCommerce and repeat the test with a different event key to check coexistence.

The automated behavioral tests also cover lease expiration, interrupted processing, existing file reuse, and ambiguous email handoffs.

## Architecture

The workflow is separated into interfaces for persistence, storage, and mail transport. WordPress specific adapters implement these contracts. Action Scheduler executes the queued work while the database, rather than the queue job ID, controls event identity and delivery state.

The sample mail transport is deliberately a **no-op**. Its accepted state means a simulated handoff completed, not that an actual email was delivered.

## Scope and limitations

This is a **proof of concept, not a production document management system**. It generates fictional text files, not PDFs. It uses local file storage rather than cloud object storage and does not implement a security boundary for stored files across hosting configurations.

It does not provide real email delivery, an append only audit log, access controls, migrations, retention policies, production monitoring, or an operator reconciliation interface. A production email integration would need provider specific idempotency or reconciliation for uncertain delivery outcomes.

Do not use this sample for payroll, legal evidence, personal records, or other sensitive documents.
