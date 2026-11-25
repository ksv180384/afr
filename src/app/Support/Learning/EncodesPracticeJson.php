<?php

namespace App\Support\Learning;

trait EncodesPracticeJson
{
    protected function asJson($value): string
    {
        return PracticeJson::encode($value);
    }
}
