<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\PushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;

/** Turns pop-up notifications on or off for the device making the request (D75). */
class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        PushSubscription::updateOrCreate(['endpoint_hash' => PushSubscription::hash($data['endpoint'])], [
            'user_id' => $request->user()->id, // a shared device follows whoever signed in last
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'device' => str($request->userAgent())->limit(115)->toString(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(PushSubscriptionRequest $request): JsonResponse
    {
        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hash($request->validated('endpoint')))->delete();

        return response()->json(['ok' => true]);
    }
}
