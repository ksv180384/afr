<?php

namespace Tests;

use App\Models\User\Gender;
use App\Models\User\Rang;
use App\Models\User\UserConfigsView;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gender::query()->firstOrCreate([
            'title' => 'Не указан',
        ]);

        Rang::query()->firstOrCreate(
            ['alias' => 'polzovatel'],
            ['title' => 'Пользователь'],
        );

        UserConfigsView::query()->firstOrCreate(
            ['alias' => UserConfigsView::NE_POKAZYVAT_NIKOMU],
            ['title' => 'Не показывать никому'],
        );
    }
}
