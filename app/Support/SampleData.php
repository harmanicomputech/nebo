<?php

namespace App\Support;

use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sample data (D71): realistic records for trying the system, with no
 * visible marker. While the sample loader runs, every row Eloquent creates
 * is noted in `sample_records` (in order), so it can all be removed later
 * from Settings → System without touching anything people entered.
 */
class SampleData
{
    /** Password of the sample sign-in accounts (shown on the System page). */
    public const PASSWORD = 'NeboStage@2026';

    /** Reference counters and the table whose records use them. */
    private const SEQUENCES = ['request' => 'event_requests', 'event' => 'events', 'quotation' => 'quotations',
        'load_list' => 'load_lists', 'maintenance' => 'maintenance_records', 'trip' => 'logistics_trips'];

    /** Real records the sample activity changes (bookings, check-ins, repairs, day rates); snapshotted and restored (D73). */
    private const SNAPSHOT = ['equipment', 'equipment_assets', 'stock_levels'];

    private static bool $recording = false;

    /** @var list<array{table_name: string, key_name: string, record_key: string}> */
    private static array $buffer = [];

    private static ?array $emails = null;

    /** Runs the callback and notes every record it creates as sample data. */
    public static function record(callable $callback): mixed
    {
        self::snapshot();
        self::$recording = true;
        self::$buffer = [];
        try {
            $result = $callback();
            foreach (array_chunk(self::$buffer, 500) as $chunk) {
                DB::table('sample_records')->insert($chunk);
            }

            return $result;
        } finally {
            self::$recording = false;
            self::$buffer = [];
            self::$emails = null;
        }
    }

    /**
     * Before the first sample record is created, keep a copy of the real
     * equipment, units and stock so clearing can put them back exactly.
     */
    private static function snapshot(): void
    {
        if (DB::table('sample_records')->exists() || DB::table('sample_snapshots')->exists()) {
            return;
        }
        foreach (self::SNAPSHOT as $table) {
            DB::table($table)->orderBy('id')->chunk(500, fn ($rows) => DB::table('sample_snapshots')->insert(
                $rows->map(fn ($row) => ['table_name' => $table, 'record_key' => (string) $row->id, 'data' => json_encode($row)])->all()
            ));
        }
    }

    /** Puts snapshotted real records back as they were (rows deleted since are left deleted). */
    private static function restoreSnapshot(): void
    {
        DB::table('sample_snapshots')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                $data = collect(json_decode($row->data, true))->except(['id', 'created_at'])->all();
                DB::table($row->table_name)->where('id', $row->record_key)->update($data);
            }
        });
        DB::table('sample_snapshots')->delete();
    }

    /** Called for every Eloquent "created" event. */
    public static function capture(Model $model): void
    {
        if (self::$recording && $model->getKey() !== null) {
            self::$buffer[] = ['table_name' => $model->getTable(), 'key_name' => $model->getKeyName(), 'record_key' => (string) $model->getKey()];
        }
    }

    public static function exists(): bool
    {
        try {
            return Schema::hasTable('sample_records') && DB::table('sample_records')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, int> table => number of sample rows */
    public static function counts(): array
    {
        return DB::table('sample_records')->selectRaw('table_name, count(*) as total')->groupBy('table_name')->pluck('total', 'table_name')->map(fn ($n) => (int) $n)->all();
    }

    /** Sample sign-in accounts, for the System page. */
    public static function accounts(): Collection
    {
        return User::query()->whereIn('id', self::keys('users'))->with('roles')->orderBy('name')->get();
    }

    /**
     * Email addresses that belong to sample people and customers; mail to
     * them is never sent (they look real, so they might be).
     *
     * @return array<string, true>
     */
    public static function emails(): array
    {
        if (self::$emails !== null) {
            return self::$emails;
        }
        if (! self::exists()) {
            return self::$emails = [];
        }

        $emails = collect(['users', 'customers', 'event_requests'])
            ->flatMap(fn (string $table) => DB::table($table)->whereIn('id', self::keys($table))->pluck('email'))
            ->filter()->map(fn ($e) => mb_strtolower($e));

        return self::$emails = $emails->flip()->map(fn () => true)->all();
    }

    /**
     * Removes every sample record, newest first, so rows are deleted before
     * the rows they point to. Refuses when no real administrator would be
     * left to sign in.
     *
     * @return array{ok: bool, message: string, removed?: int}
     */
    public static function clear(?User $by = null, bool $includeDependents = false): array
    {
        $sampleUsers = self::keys('users');
        $realAdmins = User::query()->active()->role(PermissionCatalog::SUPER_ADMIN)->whereNotIn('id', $sampleUsers)->count();
        if ($realAdmins === 0) {
            return ['ok' => false, 'message' => 'Create your own administrator account first (Users → Add user, role Super Administrator): every administrator is a sample account.'];
        }

        @set_time_limit(300);
        $dependents = self::dependents();
        $ownCount = array_sum(array_map('count', $dependents));
        if ($dependents && ! $includeDependents) {
            return ['ok' => false, 'message' => 'Some records you added use sample data: '.self::describe(self::blockers()).'. Clear them together with the sample data, or switch them to your own customers, equipment and events first. Nothing was removed.'];
        }
        $removed = 0;

        try {
            DB::transaction(function () use ($sampleUsers, $dependents, &$removed) {
                // The person's own records that use sample data go first. Their
                // order across tables is unknown, so retry until all are gone.
                for ($pass = 0; $dependents && $pass < 10; $pass++) {
                    foreach ($dependents as $table => $ids) {
                        try {
                            foreach (array_chunk($ids, 500) as $chunk) {
                                $removed += DB::table($table)->whereIn('id', $chunk)->delete();
                            }
                            unset($dependents[$table]);
                        } catch (QueryException) {
                            // Still referenced by another dependent; next pass.
                        }
                    }
                }
                if ($dependents) {
                    throw new \RuntimeException('Could not remove: '.implode(', ', array_keys($dependents)));
                }

                $run = [];
                $flush = function () use (&$run, &$removed) {
                    if ($run) {
                        $removed += DB::table($run['table'])->whereIn($run['key'], $run['ids'])->delete();
                    }
                    $run = [];
                };

                DB::table('sample_records')->orderByDesc('id')->chunk(1000, function ($rows) use (&$run, $flush) {
                    foreach ($rows as $row) {
                        if (! $run || $run['table'] !== $row->table_name || count($run['ids']) >= 500) {
                            $flush();
                            $run = ['table' => $row->table_name, 'key' => $row->key_name, 'ids' => []];
                        }
                        $run['ids'][] = $row->record_key;
                    }
                });
                $flush();

                // Role assignments are pivot rows, not models.
                foreach (array_chunk($sampleUsers, 500) as $ids) {
                    DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $ids)->delete();
                    DB::table('model_has_permissions')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $ids)->delete();
                    DB::table('sessions')->whereIn('user_id', $ids)->delete();
                }

                // Reference numbers start again at 1 where no records are left.
                foreach (self::SEQUENCES as $key => $table) {
                    if (Schema::hasTable($table) && ! DB::table($table)->exists()) {
                        DB::table('sequences')->where('key', $key)->delete();
                    }
                }
                if (! DB::table('equipment_assets')->exists()) {
                    DB::table('sequences')->where('key', 'like', 'asset:%')->delete(); // asset tags (ML-0001…)
                }

                DB::table('sample_records')->delete();
                self::restoreSnapshot();
            });
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'Sample data could not be cleared, probably because records you added use sample equipment, customers or events. Nothing was removed. ('.str($e->getMessage())->limit(160).')'];
        }

        app(Lookups::class)->flush();
        Settings::flush();
        self::$emails = null;
        Audit::record('sample_data_cleared', ($by?->name ?? 'The system').' cleared the sample data ('.number_format($removed).' records)');

        return ['ok' => true, 'removed' => $removed, 'message' => 'Sample data cleared: '.number_format($removed).' records removed.'
            .($ownCount ? ' That includes '.number_format($ownCount).' of your records that used sample data.' : ' Your own records are untouched.')];
    }

    /**
     * Records people added that depend on sample records (directly or
     * through each other) by a key that would stop the delete: a real event
     * for a sample customer, its allocations of sample equipment, their
     * ledger entries. Rows that hang off a sample record by a cascading key
     * (trip items, crew lists) go with it and are not listed.
     *
     * @return array<string, list<string>> table => keys
     */
    public static function dependents(): array
    {
        $set = DB::table('sample_records')->get(['table_name', 'record_key'])
            ->groupBy('table_name')->map(fn ($rows) => $rows->pluck('record_key')->flip()->all())->all();
        $extra = [];
        $schema = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn ($t) => $t === 'sample_records' || ! Schema::hasColumn($t, 'id'))
            ->mapWithKeys(fn ($t) => [$t => array_values(array_filter(Schema::getForeignKeys($t), fn ($fk) => count($fk['columns']) === 1))]);

        do {
            $changed = false;
            foreach ($schema as $table => $foreignKeys) {
                $owners = array_filter($foreignKeys, fn ($fk) => strtolower((string) ($fk['on_delete'] ?? '')) === 'cascade');
                foreach ($foreignKeys as $fk) {
                    $onDelete = strtolower((string) ($fk['on_delete'] ?? ''));
                    if (in_array($onDelete, ['cascade', 'set null'], true) || empty($set[$fk['foreign_table']])) {
                        continue;
                    }
                    foreach (array_chunk(array_keys($set[$fk['foreign_table']]), 500) as $chunk) {
                        $ids = DB::table($table)->whereIn($fk['columns'][0], $chunk)->pluck('id');
                        foreach ($ids as $id) {
                            $id = (string) $id;
                            if (isset($set[$table][$id])) {
                                continue;
                            }
                            // Goes with a cascading owner that is being deleted anyway?
                            $row = $owners ? (array) DB::table($table)->where('id', $id)->first() : [];
                            foreach ($owners as $owner) {
                                if (isset($set[$owner['foreign_table']][(string) ($row[$owner['columns'][0]] ?? '')])) {
                                    continue 2;
                                }
                            }
                            $set[$table][$id] = true;
                            $extra[$table][] = $id;
                            $changed = true;
                        }
                    }
                }
            }
        } while ($changed);

        return $extra;
    }

    /** @return array<string, int> "equipment allocations" => 2, for messages */
    public static function blockers(): array
    {
        return collect(self::dependents())->mapWithKeys(fn (array $ids, string $table) => [
            str_replace('_', ' ', count($ids) === 1 ? Str::singular($table) : $table) => count($ids),
        ])->all();
    }

    /** @param  array<string, int>  $counts */
    public static function describe(array $counts): string
    {
        return collect($counts)->map(fn ($n, $what) => number_format($n)." {$what}")->join(', ', ' and ');
    }

    /** @return list<string> */
    private static function keys(string $table): array
    {
        try {
            return DB::table('sample_records')->where('table_name', $table)->pluck('record_key')->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
