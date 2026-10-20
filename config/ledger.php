<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ledger Application Settings
    |--------------------------------------------------------------------------
    |
    | Ledger-specific policy that used to live hardcoded across controllers,
    | jobs, seeders and views. Most of this is app policy rather than
    | per-deployment secrets, so it lives here (not .env). Only the handful
    | of values that genuinely differ per host are pulled from the
    | environment below.
    |
    | Ledger was called Docket until October 2026. Every LEDGER_* variable
    | still falls back to its old DOCKET_* name, so an existing .env keeps
    | working until it is updated.
    |
    */

    /*
    | The single seeded user. DatabaseSeeder creates exactly one account and
    | there is no registration flow, so these describe the owner.
    |
    | These values are used ONLY to bootstrap the account the first time it is
    | seeded, and only into an empty users table. Once the
    | user exists, re-seeding leaves it untouched and any in-app password change
    | is stored, hashed, in the database — the DB is the source of truth from
    | then on. The seeder never creates a second account: once any user
    | exists it leaves users alone, whatever email this config names.
    */
    'user' => [
        // ?: not the env() default param: a present-but-empty line in .env
        // (e.g. LEDGER_USER_NAME=) would otherwise seed an empty string
        // rather than falling back to these defaults.
        'name' => env('LEDGER_USER_NAME') ?: env('DOCKET_USER_NAME') ?: 'Ledger Owner',
        'email' => env('LEDGER_USER_EMAIL') ?: env('DOCKET_USER_EMAIL') ?: 'owner@example.com',
        // No default: a seeded account with a published password would open
        // the whole ledger to anyone who read the source. DatabaseSeeder
        // refuses to create the owner without one.
        'password' => env('LEDGER_USER_PASSWORD') ?: env('DOCKET_USER_PASSWORD') ?: null,
    ],

    /*
    | Installer state: the "installed" marker that keeps /install closed even
    | if the database is briefly unreadable, the one-time setup code the web
    | installer asks for, and the lock that serialises installations. Outside
    | public/, and readable only from the server.
    */
    'install' => [
        'state_path' => env('LEDGER_INSTALL_STATE_PATH') ?: storage_path('app/install'),
    ],

    /*
    | Reporting/base currency. Everything is stored and totalled in GBP
    | minor units; this is the assumed currency when none is given and the
    | short list offered in the currency dropdowns.
    */
    'base_currency' => 'GBP',
    'currency_symbol' => '£',
    'currencies' => ['GBP', 'USD', 'EUR'],
    'currency_symbols' => [
        'GBP' => '£',
        'USD' => '$',
        'EUR' => '€',
    ],

    /*
    | Receipt upload constraints, shared by the capture flow and the
    | add-receipt form so both accept exactly the same files.
    */
    'receipts' => [
        // Passed straight into Laravel's `mimes:` validation rule.
        'mimes' => 'jpg,jpeg,png,webp,heic,pdf',
        // Max upload size in kilobytes (10 MB).
        'max_kb' => 10240,
    ],

    /*
    | Largest amount (in major units) accepted on a single transaction line.
    | A sanity cap, not an accounting rule.
    */
    'max_amount' => 42000000,

    /*
    | Queued OCR job tuning. Retries cover transient Vision API blips /
    | rate limits; backoff is the seconds to wait between each try.
    | Tesseract is the free, local fallback used when Google Vision has no
    | credentials configured or its call errors; the binary name/path
    | differs by host, so it's env-overridable like the export python_bin.
    */
    'ocr' => [
        'tries' => 3,
        'backoff' => [10, 60],
        'tesseract_binary' => env('LEDGER_TESSERACT_BIN', env('DOCKET_TESSERACT_BIN', 'tesseract')),
        // How long a capture's pending-receipt cache entry (photo path +
        // OCR result, keyed by token) survives before /expenses/create can
        // no longer prefill from it. The file on disk outlives this — see
        // ledger:prune-captures.
        'capture_ttl' => 3600,
    ],

    /*
    | Accounts Tracker XLSX export toolchain. The venv python and script
    | paths differ by host (e.g. the ARM OCI box), so both are env-overridable.
    */
    'export' => [
        'python_bin' => env(
            'LEDGER_PYTHON_BIN',
            env('DOCKET_PYTHON_BIN', base_path('python/venv/bin/python'))
        ),
        'script' => env(
            'LEDGER_PYTHON_SCRIPT',
            env('DOCKET_PYTHON_SCRIPT', base_path('python/scripts/populate_tracker.py'))
        ),
    ],

    /*
    | Accounts Tracker XLSX import toolchain. Reuses the same venv as the
    | export, but the script path is separate so either can be overridden.
    */
    'import' => [
        'python_bin' => env(
            'LEDGER_PYTHON_BIN',
            env('DOCKET_PYTHON_BIN', base_path('python/venv/bin/python'))
        ),
        'script' => env(
            'LEDGER_PYTHON_IMPORT_SCRIPT',
            env('DOCKET_PYTHON_IMPORT_SCRIPT', base_path('python/scripts/import_tracker.py'))
        ),
    ],

];
