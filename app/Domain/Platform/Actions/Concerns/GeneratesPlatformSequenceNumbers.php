<?php

namespace App\Domain\Platform\Actions\Concerns;

use App\Domain\Platform\Models\PlatformSequence;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Shared by CreateQuotation and PayQuotation — concurrency-safe sequential
 * numbering (CLAUDE.md §24), the same "insert-if-missing, then
 * lockForUpdate() read+increment" idiom as CheckoutSale's InvoiceSequence
 * use, just global rather than per-branch since the platform itself has no
 * branches.
 */
trait GeneratesPlatformSequenceNumbers
{
    private function nextPlatformNumber(string $sequenceType): string
    {
        $financialYear = $this->financialYearFor(now());

        try {
            (new PlatformSequence(['sequence_type' => $sequenceType, 'financial_year' => $financialYear, 'next_number' => 1]))->save();
        } catch (UniqueConstraintViolationException) {
            // Already exists — fine, the lock+read below picks it up.
        }

        $sequence = PlatformSequence::where('sequence_type', $sequenceType)
            ->where('financial_year', $financialYear)
            ->lockForUpdate()
            ->firstOrFail();

        $number = $sequence->next_number;
        $sequence->next_number = $number + 1;
        $sequence->save();

        $prefix = $sequenceType === 'invoice' ? 'PINV' : 'QUO';

        return sprintf('%s/%s/%06d', $prefix, $financialYear, $number);
    }

    private function financialYearFor(\DateTimeInterface $date): string
    {
        $year = (int) $date->format('Y');
        $startYear = ((int) $date->format('n') >= 4) ? $year : $year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }
}
