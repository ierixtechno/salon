<?php

namespace App\Jobs;

use App\Domain\Core\Actions\SendNotification;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\DataExport;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Tenant data export/portability (CLAUDE.md §66). Deliberately scoped to
 * the three highest-value business record types (customers, appointments,
 * invoices) rather than every one of the 50+ domain tables built across
 * Phases 1-13 — a full raw-table dump is a much larger, separately
 * decidable undertaking; this covers what a tenant most plausibly wants
 * for backup or migration purposes.
 *
 * Runs `withoutGlobalScope(TenantScope::class)` throughout: this job has
 * no `web` session (CLAUDE.md §28/§11 — same reasoning as every other
 * console/queue-context action in this app), so it scopes explicitly by
 * the DataExport row's own already-trusted tenant_id instead.
 */
class GenerateTenantDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $dataExportId) {}

    public function handle(): void
    {
        $export = DataExport::withoutGlobalScope(TenantScope::class)->find($this->dataExportId);
        if (! $export || $export->status !== 'pending') {
            return;
        }

        $export->status = 'processing';
        $export->save();

        $tenantId = $export->tenant_id;
        $workDir = storage_path("app/private/exports-tmp/{$export->id}");

        try {
            if (! is_dir($workDir)) {
                mkdir($workDir, 0755, true);
            }

            $this->writeCustomersCsv($tenantId, "{$workDir}/customers.csv");
            $this->writeAppointmentsCsv($tenantId, "{$workDir}/appointments.csv");
            $this->writeInvoicesCsv($tenantId, "{$workDir}/invoices.csv");

            $relativePath = "exports/{$tenantId}/{$export->id}.zip";
            $absoluteZipPath = Storage::disk('local')->path($relativePath);
            if (! is_dir(dirname($absoluteZipPath))) {
                mkdir(dirname($absoluteZipPath), 0755, true);
            }

            $zip = new ZipArchive;
            $zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach (['customers.csv', 'appointments.csv', 'invoices.csv'] as $file) {
                $zip->addFile("{$workDir}/{$file}", $file);
            }
            $zip->close();

            $export->status = 'completed';
            $export->file_path = $relativePath;
            $export->completed_at = now();
            $export->expires_at = now()->addDays(7);
            $export->save();

            if ($export->requested_by) {
                app(SendNotification::class)->execute(
                    tenantId: $tenantId,
                    channel: 'in_app',
                    recipientType: 'user',
                    recipientId: $export->requested_by,
                    toAddress: null,
                    subject: 'Your data export is ready',
                    body: 'Your requested data export has finished and is ready to download. It will be available for 7 days.',
                    referenceType: DataExport::class,
                    referenceId: $export->id,
                );
            }
        } catch (Throwable $e) {
            $export->status = 'failed';
            $export->failure_reason = $e->getMessage();
            $export->save();
        } finally {
            foreach (glob("{$workDir}/*.csv") ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($workDir)) {
                rmdir($workDir);
            }
        }
    }

    private function writeCustomersCsv(int $tenantId, string $path): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['ID', 'Name', 'Email', 'Phone', 'Date of Birth', 'Gender', 'Source', 'Marketing Consent', 'Active', 'Created At']);

        Customer::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->chunk(500, function ($customers) use ($handle) {
                foreach ($customers as $c) {
                    fputcsv($handle, [
                        $c->id, $c->name, $c->email, $c->phone, $c->date_of_birth, $c->gender,
                        $c->source, $c->marketing_consent ? 'Yes' : 'No', $c->is_active ? 'Yes' : 'No', $c->created_at,
                    ]);
                }
            });

        fclose($handle);
    }

    private function writeAppointmentsCsv(int $tenantId, string $path): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['ID', 'Branch', 'Customer', 'Service', 'Employee', 'Starts At', 'Status', 'Price', 'Source', 'Created At']);

        Appointment::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)
            ->with(['branch:id,name', 'customer:id,name', 'service:id,name', 'employee:id,name'])
            ->orderBy('id')
            ->chunk(500, function ($appointments) use ($handle) {
                foreach ($appointments as $a) {
                    fputcsv($handle, [
                        $a->id, $a->branch?->name, $a->customer?->name, $a->service?->name, $a->employee?->name,
                        $a->starts_at, $a->status, $a->price, $a->source, $a->created_at,
                    ]);
                }
            });

        fclose($handle);
    }

    private function writeInvoicesCsv(int $tenantId, string $path): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['ID', 'Invoice Number', 'Branch', 'Customer', 'Status', 'Subtotal', 'Discount', 'Tax', 'Grand Total', 'Finalized At', 'Created At']);

        Invoice::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)
            ->with(['branch:id,name', 'customer:id,name'])
            ->orderBy('id')
            ->chunk(500, function ($invoices) use ($handle) {
                foreach ($invoices as $i) {
                    fputcsv($handle, [
                        $i->id, $i->invoice_number, $i->branch?->name, $i->customer?->name ?? $i->customer_name, $i->status,
                        $i->subtotal, $i->discount_total, $i->tax_total, $i->grand_total, $i->finalized_at, $i->created_at,
                    ]);
                }
            });

        fclose($handle);
    }
}
