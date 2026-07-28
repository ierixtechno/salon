<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A valid Indian mobile number: exactly 10 digits, first digit 6-9 (the
 * only range mobile numbers are issued in; landline/other series are
 * deliberately out of scope — every phone field in this app is a contact
 * number for SMS/WhatsApp/call purposes). Stored as the bare 10 digits,
 * no country code or formatting — `+91` is a display-only prefix in
 * <x-phone-input>, never part of the persisted value.
 */
class IndianMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^[6-9][0-9]{9}$/', (string) $value)) {
            $fail('The :attribute must be a valid 10-digit Indian mobile number.');
        }
    }
}
