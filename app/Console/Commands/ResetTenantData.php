<?php

namespace App\Console\Commands;

use App\Domain\Platform\Actions\RunBackup;
use App\Domain\Platform\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes every tenant and everything that belongs to one, so the platform
 * can be tested from a clean start. IRREVERSIBLE — hence: a full backup is
 * taken first (and the wipe is refused if that fails), and the operator
 * must type RESET.
 *
 * Removed : tenants and all their data (users, roles, branches, customers,
 *           invoices, payments, stock, ...), their subscriptions, platform
 *           invoices and quotations, per-tenant audit + error logs, queued
 *           and failed jobs, uploaded files, and the quotation/invoice
 *           number sequences (numbering starts again from 1).
 * Kept    : Super Admin accounts, subscription plans, modules, features,
 *           permissions, platform settings, and the backup history/files.
 *
 * Tables are discovered from the schema (anything with a `tenant_id`), so a
 * table added by a later migration is covered without editing this file.
 */
class ResetTenantData extends Command
{
    protected $signature = 'platform:reset-tenants {--force : Skip the typed confirmation} {--no-backup : Do NOT take a backup first (not recommended)}';

    protected $description = 'DANGER: delete ALL tenants and their data (subscriptions, invoices, quotations, everything) for a fresh start. Super Admin, plans and modules are kept.';

    /** Tables cleared completely: links/queues that carry no tenant_id of their own. */
    private const WHOLE_TABLES = [
        'branch_modules', 'branch_user', 'membership_plan_branches', 'membership_plan_modules',
        'membership_plan_services', 'package_services', 'service_branch', 'service_user',
        'jobs', 'failed_jobs', 'job_batches', 'platform_sequences', 'error_logs',
    ];

    public function handle(RunBackup $backup): int
    {
        $tenants = DB::table('tenants')->count();

        $this->warn('This will PERMANENTLY DELETE all tenants and their data.');
        $this->line("  tenants: {$tenants}");
        $this->line('  users: '.DB::table('users')->count().' · invoices: '.DB::table('platform_invoices')->count().' platform / '.DB::table('invoices')->count().' sales · quotations: '.DB::table('quotations')->count());
        $this->line('Kept: Super Admin accounts, subscription plans, modules, features, backups.');

        if (! $this->option('force') && $this->ask('Type RESET to continue') !== 'RESET') {
            $this->info('Cancelled — nothing was changed.');

            return self::FAILURE;
        }

        if (! $this->option('no-backup')) {
            $this->line('Taking a backup first...');
            $run = $backup->execute('manual');

            if ($run->status !== BackupRun::SUCCESS) {
                $this->error('The backup failed, so nothing was deleted. Fix the backup (or pass --no-backup if you really do not need one) and run this again.');

                return self::FAILURE;
            }
            $this->info("Backup saved: {$run->file_name}");
        }

        $database = DB::getDatabaseName();
        $tenantTables = collect(DB::select(
            'SELECT DISTINCT table_name AS name FROM information_schema.columns WHERE table_schema = ? AND column_name = ?',
            [$database, 'tenant_id'],
        ))->pluck('name')->reject(fn ($name) => in_array($name, self::WHOLE_TABLES, true))->values();

        $cleared = [];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tenantTables as $table) {
                // NULL tenant_id = a platform-level row (e.g. a Super Admin action); keep those.
                $cleared[$table] = DB::table($table)->whereNotNull('tenant_id')->delete();
            }

            foreach (self::WHOLE_TABLES as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $cleared[$table] = DB::table($table)->delete();
                }
            }

            DB::table('role_has_permissions')->whereNotIn('role_id', DB::table('roles')->select('id'))->delete();
            DB::table('password_reset_tokens')->whereNotIn('email', DB::table('platform_admins')->select('email'))->delete();
            $cleared['tenants'] = DB::table('tenants')->delete();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Start ids (and so invoice/branch ids) from 1 again. ALTER is DDL, which
        // would break a wrapping test transaction, so skip it under PHPUnit.
        if (! app()->runningUnitTests()) {
            foreach (array_merge(['tenants'], $tenantTables->all(), self::WHOLE_TABLES) as $table) {
                if (DB::getSchemaBuilder()->hasTable($table) && DB::table($table)->doesntExist()) {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                }
            }
        }

        // Uploaded files (expense attachments, data exports, ...). Backups live elsewhere.
        $disk = Storage::disk('local');
        foreach ($disk->directories() as $directory) {
            $disk->deleteDirectory($directory);
        }
        foreach ($disk->files() as $file) {
            if ($file !== '.gitignore') {
                $disk->delete($file);
            }
        }

        Artisan::call('cache:clear');

        $this->info('Done. '.array_sum($cleared).' rows removed. The platform is fresh: register a new tenant to start testing.');

        return self::SUCCESS;
    }
}
