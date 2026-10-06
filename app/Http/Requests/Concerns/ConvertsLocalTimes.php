<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;
use Throwable;

/**
 * Datetime inputs are entered in Lagos time; convert them to UTC before
 * validation and storage (D20, D35). Invalid values pass through so the
 * date rule can report them.
 */
trait ConvertsLocalTimes
{
    /**
     * @param  list<string>  $fields
     */
    protected function convertLocalTimes(array $fields): void
    {
        $converted = [];

        foreach ($fields as $field) {
            $value = $this->input($field);
            try {
                $converted[$field] = filled($value) ? Carbon::parse((string) $value, config('nebo.display_timezone'))->utc()->toDateTimeString() : null;
            } catch (Throwable) {
                $converted[$field] = $value;
            }
        }

        $this->merge($converted);
    }
}
