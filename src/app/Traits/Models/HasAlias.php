<?php

namespace App\Traits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasAlias
{
    protected static function bootHasAlias()
    {

        static::creating(function (Model $model){
            $model->makeSlug();
        });
    }

    protected function makeSlug(): void
    {
        if(!$this->{$this->slugColumn()}){
            $slug = $this->slugUniq(
                str($this->{$this->slugFrom()})->slug()->value()
            );

            $this->{$this->slugColumn()} = $slug;
        }
    }

    protected function slugColumn(): string
    {
        return 'alias';
    }

    protected function slugFrom(): string
    {
        return 'title';
    }

    private function slugUniq(string $slug): string
    {
        $originalSlug = $slug;
        $i = 0;

        while ($this->isSlugExists($slug)){
            $i++;

            $slug = $originalSlug . '-' . $i;
        }

        return $slug;
    }

    private function isSlugExists(string $slug): bool
    {
        $query = $this->newQuery()
            ->where(self::slugColumn(), $slug)
            //->where($this->getKeyName(), '!=', $this->getKey())
            ->withoutGlobalScopes();

        return $query->exists();
    }
}
