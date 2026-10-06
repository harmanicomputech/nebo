<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Note;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer profiles (D63). Merging moves everything that belongs to the
 * duplicate onto the kept profile, fills the kept profile's blanks, and
 * archives the duplicate, with an audit entry. Nothing is deleted.
 */
class CustomerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Customer $customer, array $data): Customer
    {
        $data['email'] = filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null;
        $data['phone'] = filled($data['phone'] ?? null) ? (PhoneNumber::normalize($data['phone']) ?? trim($data['phone'])) : null;

        $customer ??= new Customer(['source' => 'internal']);
        $customer->fill($data)->save();

        return $customer;
    }

    public function markReviewed(Customer $customer): void
    {
        $customer->update(['needs_review' => false]);
    }

    public function merge(User $actor, Customer $keep, Customer $duplicate): void
    {
        if ($keep->is($duplicate)) {
            throw ValidationException::withMessages(['duplicate_id' => 'Choose a different customer to merge.']);
        }

        DB::transaction(function () use ($keep, $duplicate) {
            $moved = [
                'requests' => EventRequest::where('customer_id', $duplicate->id)->update(['customer_id' => $keep->id]),
                'events' => Event::withTrashed()->where('customer_id', $duplicate->id)->update(['customer_id' => $keep->id]),
                'quotations' => Quotation::withTrashed()->where('customer_id', $duplicate->id)->update(['customer_id' => $keep->id]),
                'notes' => Note::where(['notable_type' => $duplicate->getMorphClass(), 'notable_id' => $duplicate->id])->update(['notable_id' => $keep->id]),
                'documents' => Document::withTrashed()->where(['documentable_type' => $duplicate->getMorphClass(), 'documentable_id' => $duplicate->id])->update(['documentable_id' => $keep->id]),
            ];

            foreach (['company', 'type', 'email', 'phone', 'address', 'city', 'state'] as $field) {
                if (blank($keep->{$field}) && filled($duplicate->{$field})) {
                    $keep->{$field} = $duplicate->{$field};
                }
            }
            if (filled($duplicate->remarks)) {
                $keep->remarks = trim(($keep->remarks ? $keep->remarks."\n\n" : '')."From merged profile #{$duplicate->id}: ".$duplicate->remarks);
            }
            $keep->needs_review = false;
            $keep->save();
            $duplicate->update(['needs_review' => false]);
            $duplicate->delete();

            Audit::record('customer_merged', "Merged customer #{$duplicate->id} ({$duplicate->displayName()}) into #{$keep->id}", $keep, ['merged_id' => $duplicate->id], $moved);
        });
    }
}
