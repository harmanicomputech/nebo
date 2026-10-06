<?php

namespace App\Services\Booking;

use App\Models\Customer;
use App\Support\PhoneNumber;

/**
 * Finds the existing customer for a returning requester instead of creating
 * duplicates: normalised email first, then normalised phone. If the details
 * differ from what's on file (another contact person or company), the
 * customer is flagged for review rather than silently merged or overwritten.
 */
class CustomerMatcher
{
    /**
     * @param  array{name: string, company?: ?string, email?: ?string, phone?: ?string}  $details
     */
    public function findOrCreate(array $details, string $source = 'public_form'): Customer
    {
        $email = isset($details['email']) ? mb_strtolower(trim($details['email'])) : null;
        $phone = PhoneNumber::normalize($details['phone'] ?? null);
        $company = trim((string) ($details['company'] ?? '')) ?: null;
        $name = trim($details['name']);

        $customer = ($email ? Customer::where('email', $email)->first() : null)
            ?? ($phone ? Customer::where('phone', $phone)->first() : null);

        if (! $customer) {
            return Customer::create(['name' => $name, 'company' => $company, 'email' => $email, 'phone' => $phone, 'source' => $source]);
        }

        $differs = mb_strtolower($customer->name) !== mb_strtolower($name)
            || ($company && $customer->company && mb_strtolower($customer->company) !== mb_strtolower($company));

        $customer->fill(array_filter([
            'email' => $customer->email ?: $email,
            'phone' => $customer->phone ?: $phone,
            'company' => $customer->company ?: $company,
        ]));

        if ($differs) {
            $customer->needs_review = true;
        }

        if ($customer->isDirty()) {
            $customer->save();
        }

        return $customer;
    }
}
