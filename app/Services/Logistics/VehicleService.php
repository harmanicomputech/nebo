<?php

namespace App\Services\Logistics;

use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class VehicleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Vehicle $vehicle, array $data): Vehicle
    {
        if ($vehicle && ($data['status'] ?? null) !== VehicleStatus::Active->value) {
            $this->assertNoActiveTrips($vehicle, 'status', 'Reassign its upcoming trips before taking it off the road');
        }

        $vehicle ??= new Vehicle;
        $vehicle->fill($data)->save();

        return $vehicle;
    }

    public function archive(Vehicle $vehicle): void
    {
        $this->assertNoActiveTrips($vehicle, 'vehicle', 'Reassign its upcoming trips before archiving it');
        $vehicle->delete();
    }

    private function assertNoActiveTrips(Vehicle $vehicle, string $field, string $message): void
    {
        $trips = $vehicle->trips()->whereIn('status', TripStatus::activeValues())->pluck('reference');

        if ($trips->isNotEmpty()) {
            throw ValidationException::withMessages([$field => "{$vehicle->name} is booked on ".$trips->implode(', ').". {$message}."]);
        }
    }
}
