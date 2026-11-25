<?php

use App\Support\Learning\LessonResult;
use App\Support\Learning\PracticeJson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OPERATIONS = 'learning_run_operations';

    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        if ($sqlite) {
            Schema::table(self::OPERATIONS, function (Blueprint $t) {
                $t->binary('payload_hash')->change();
                $t->json('receipt')->nullable()->change();
            });
            DB::connection()->getPdo()->sqliteCreateFunction('practice_unhex',
                static fn (string $hex) => ctype_xdigit($hex) && strlen($hex) === 64 ? hex2bin($hex) : null, 1);
        } else {
            // A direct CHAR(64) -> BINARY(32) conversion would truncate the hexadecimal text.
            DB::statement('ALTER TABLE learning_run_operations MODIFY payload_hash VARBINARY(64) NOT NULL, MODIFY receipt JSON NULL');
        }

        $bytes = $sqlite ? 'length(CAST(payload_hash AS BLOB))' : 'OCTET_LENGTH(payload_hash)';
        $unhex = $sqlite ? 'CAST(practice_unhex(payload_hash) AS BLOB)' : 'UNHEX(payload_hash)';
        $pointer = $sqlite
            ? "json_type(receipt) = 'object' AND json_extract(receipt, '$.run_id') = run_id AND (SELECT count(*) FROM json_each(receipt)) = 1"
            : "JSON_TYPE(receipt) = 'OBJECT' AND JSON_LENGTH(receipt) = 1 AND BINARY JSON_UNQUOTE(JSON_EXTRACT(receipt, '$.run_id')) = BINARY run_id";

        $this->operationBatches(function ($batch) use ($bytes, $unhex, $pointer) {
            if ((clone $batch)->whereRaw("($bytes NOT IN (32, 64) OR ($bytes = 64 AND $unhex IS NULL))")->exists()) {
                throw new UnexpectedValueException('Invalid SHA-256 in learning_run_operations.');
            }
            (clone $batch)->whereRaw("$bytes = 64")->update(['payload_hash' => DB::raw($unhex)]);
            (clone $batch)->whereNotNull('run_id')->whereNotNull('receipt')->whereRaw($pointer)->update(['receipt' => null]);
        });
        if (! $sqlite) {
            DB::statement('ALTER TABLE learning_run_operations MODIFY payload_hash BINARY(32) NOT NULL');
        }
        $this->lessonResults(true);
    }

    public function down(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        if (! $sqlite) {
            DB::statement('ALTER TABLE learning_run_operations MODIFY payload_hash VARBINARY(64) NOT NULL');
        }
        $this->operationBatches(function ($batch) use ($sqlite) {
            $bytes = $sqlite ? 'length(CAST(payload_hash AS BLOB))' : 'OCTET_LENGTH(payload_hash)';
            (clone $batch)->whereRaw("$bytes = 32")->update(['payload_hash' => DB::raw('LOWER(HEX(payload_hash))')]);
            (clone $batch)->whereNull('receipt')->whereNotNull('run_id')->update(['receipt' => DB::raw("JSON_OBJECT('run_id', run_id)")]);
        });
        if ($sqlite) {
            Schema::table(self::OPERATIONS, function (Blueprint $t) {
                $t->char('payload_hash', 64)->change();
                $t->json('receipt')->nullable(false)->change();
            });
        } else {
            DB::statement('ALTER TABLE learning_run_operations MODIFY payload_hash CHAR(64) NOT NULL, MODIFY receipt JSON NOT NULL');
        }
        // A finished lesson can outlive its original content. Never substitute the current lesson checksum.
        $this->lessonResults(false);
    }

    private function operationBatches(Closure $convert): void
    {
        DB::table(self::OPERATIONS)->select('id')->chunkById(2000, function ($rows) use ($convert) {
            $batch = DB::table(self::OPERATIONS)->whereBetween('id', [$rows->first()->id, $rows->last()->id]);
            DB::transaction(fn () => $convert($batch), 3);
        });
    }

    private function lessonResults(bool $compact): void
    {
        $lessons = DB::table('learning_runs')->where('status', 'completed')->whereIn('kind', ['daily', 'extra']);
        $lessons->select('id', 'result')->chunkById(250, function ($rows) use ($compact) {
            $cases = [];
            $bindings = [];
            foreach ($rows as $row) {
                if ($row->result === null) {
                    continue;
                }
                $before = json_decode($row->result, true, 512, JSON_THROW_ON_ERROR);
                $after = $compact ? LessonResult::compact($before) : LessonResult::expand($before);
                if ($after !== $before) {
                    $cases[] = 'WHEN ? THEN ?';
                    array_push($bindings, $row->id, PracticeJson::encode($after));
                }
            }
            $changes = $compact ? ['content_checksum = NULL'] : [];
            if ($cases) {
                $changes[] = 'result = CASE id '.implode(' ', $cases).' ELSE result END';
            }
            if ($changes) {
                DB::update('UPDATE learning_runs SET '.implode(', ', $changes)
                    ." WHERE status = 'completed' AND kind IN ('daily', 'extra') AND id BETWEEN ? AND ?",
                    [...$bindings, $rows->first()->id, $rows->last()->id]);
            }
        });
    }
};
