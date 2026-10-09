<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/** A browser's push subscription (PushSubscription.toJSON()). */
class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2000', 'url:https'],
            'keys.p256dh' => [$this->isMethod('DELETE') ? 'nullable' : 'required', 'string', 'max:255'],
            'keys.auth' => [$this->isMethod('DELETE') ? 'nullable' : 'required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ];
    }
}
