<?php

use Composer\Semver\Semver;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Issue #1062. Locked packages that cap `require.php` below 8.6.
 *
 * CURRENT STATE (after lock refresh):
 *   - nette/schema  v1.4.0  — allows 8.6 (>=1.4.0 supports 8.6, inside ^1.2)
 *   - nette/utils   v4.1.6  — allows 8.6 (>=4.1.6 supports 8.6, inside ^4.0)
 *   - brianium/paratest v7.20.0 — BLOCKS 8.6 (`~8.3.0 || ~8.4.0 || ~8.5.0`)
 *
 * So only brianium/paratest (dev) remains. The `test-php86` preview job still
 * needs `--ignore-platform-req=php` for this one dev dependency.
 *
 * brianium/paratest >=7.25.0 (`~8.4.0 || ~8.5.0 || ~8.6.0`) would unblock 8.6,
 * but it requires `phpunit ^13.3.4` (pest 5) — a test-stack major migration,
 * not a simple bump.
 *
 * `dragonmantank/cron-expression` is NOT a blocker: its
 * `^8.2|^8.3|^8.4|^8.5` still admits 8.6, because `^8.5` means
 * `>=8.5.0 <9.0.0`.
 *
 * This test monitors the *locked* set. When it changes, the expectation below
 * fails — pointing back at AGENTS.md and this comment to update.
 */

/**
 * @return array<string, string> package name => 'production'|'dev', for every
 *                               locked package whose `require.php` excludes 8.6
 */
function php86BlockingPackages(string $lockPath): array
{
    $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);

    $blocking = [];

    foreach (['packages' => 'production', 'packages-dev' => 'dev'] as $section => $context) {
        foreach ($lock[$section] as $package) {
            $constraint = $package['require']['php'] ?? null;

            if ($constraint !== null && ! Semver::satisfies('8.6.0', $constraint)) {
                $blocking[$package['name']] = $context;
            }
        }
    }

    ksort($blocking);

    return $blocking;
}

test('exactly three locked packages block PHP 8.6', function () {
    expect(php86BlockingPackages(base_path('composer.lock')))->toBe([
        'brianium/paratest' => 'dev',
    ]);
});

test('AGENTS.md names every package that blocks PHP 8.6', function () {
    $agents = (string) file_get_contents(base_path('AGENTS.md'));

    foreach (array_keys(php86BlockingPackages(base_path('composer.lock'))) as $package) {
        expect($agents)->toContain($package);
    }
});
