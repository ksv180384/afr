<?php

namespace App\Services\Learning;

use App\Models\Learning\LearningSession;
use App\Models\Learning\LearningUnit;
use App\Models\Learning\LearningUrlAlias;
use App\Models\User\User;
use Illuminate\Validation\ValidationException;

final class PracticeUrls
{
    public function __construct(private readonly ContentTransaction $transaction, private readonly ContentAccess $access) {}

    public static function level(string $code): string
    {
        return '/practice/'.strtolower(str_replace('_', '-', $code));
    }

    public static function unit(LearningUnit $unit): string
    {
        return self::level($unit->getRawOriginal('level')).'/'.$unit->slug;
    }

    public static function session(LearningSession $session): string
    {
        return self::unit($session->unit).'/'.$session->slug;
    }

    public static function absolute(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }

    public function rename(User $actor, string $type, int $id, array $input): array
    {
        return $this->transaction->run($actor, function () use ($actor, $type, $id, $input) {
            $target = $type === 'session' ? LearningSession::with('unit')->findOrFail($id) : LearningUnit::findOrFail($id);
            if ($target->slug !== $input['expected_slug']) {
                throw ValidationException::withMessages(['expected_slug' => 'Адрес изменён другим редактором. Обновите страницу.']);
            }
            if ($type === 'unit' ? ! $target->published_at : ! $target->versions()->whereNotNull('published_at')->exists()) {
                throw ValidationException::withMessages(['slug' => 'Сначала опубликуйте материал.']);
            }
            $slug = $input['slug'];
            $conflict = $type === 'unit'
                ? LearningUnit::where('level', $target->getRawOriginal('level'))->where('slug', $slug)->whereKeyNot($id)->exists()
                : LearningSession::where('unit_id', $target->unit_id)->where('slug', $slug)->whereKeyNot($id)->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['slug' => 'Адрес уже занят.']);
            }
            $items = $type === 'unit' ? $target->sessions()->whereNotNull('slug')->with('unit')->get() : collect([$target]);
            $oldUnitPath = $type === 'unit' ? self::unit($target) : null;
            $before = $items->mapWithKeys(fn ($session) => [$session->id => self::session($session)])->all();
            $oldSlug = $target->slug;
            $target->update(['slug' => $slug, ...($type === 'unit' ? ['revision' => $target->revision + 1] : [])]);
            if ($type === 'unit') {
                $this->alias($oldUnitPath, self::unit($target), ['unit_id' => $id]);
            }
            foreach ($items as $session) {
                $session->refresh()->load('unit');
                $this->alias($before[$session->id], self::session($session), ['session_id' => $session->id]);
            }
            $this->access->audit($actor, 'public_url_rename', [], ['type' => $type, 'id' => $id, 'slug' => $oldSlug], ['slug' => $slug]);

            return ['path' => $type === 'unit' ? self::unit($target) : self::session($target->refresh()->load('unit'))];
        });
    }

    private function alias(string $old, string $new, array $identity): void
    {
        if ($old === $new) {
            return;
        }
        $reserved = LearningUrlAlias::where('old_path', $new)->first();
        if ($reserved) {
            foreach ($identity as $field => $id) {
                if ($id !== $reserved->$field) {
                    throw ValidationException::withMessages(['slug' => 'Адрес зарезервирован прежним опубликованным материалом.']);
                }
            }
            $reserved->delete();
        }
        LearningUrlAlias::where($identity)->update(['target_path' => $new]);
        LearningUrlAlias::updateOrCreate(['old_path' => $old], ['target_path' => $new, ...$identity]);
    }
}
