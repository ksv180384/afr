<?php

namespace App\Helpers;

use App\Models\User\User;
use App\Models\User\UserConfigsView;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class Helper
{
    /**
     * @return User|null
     */
    public static function getUserData(): array|null
    {
        return Auth::check() ? Auth::user()->getMinData() : null;
    }

    /**
     * @param string|null $viewSettingName
     * @return bool
     */
    public static function isShowInfoUser(?string $viewSettingName): bool
    {
        if($viewSettingName === UserConfigsView::NE_POKAZYVAT_NIKOMU){
            return false;
        }
        elseif ($viewSettingName === UserConfigsView::ZAREGISTRIROVANNYM){
            return Auth::check();
        }
        elseif ($viewSettingName === UserConfigsView::DRUZYAM){
            return false;
        }
        elseif ($viewSettingName === UserConfigsView::VSEM){
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public static function getUserKey(): string
    {
        $userKey = Cookie::get('user_key');
        if (!$userKey) {
            // Генерируем новый UUID
            $userKey = (string) Str::uuid();

            // Устанавливаем куку для всего домена с вечным сроком действия
            Cookie::queue(Cookie::forever('user_key', $userKey, '/', null, false, false, false, 'Lax'));
        }

        return $userKey;
    }

    /**
     * Определяем написан ли текст латиницей
     * @param string $text
     * @return string
     */
    public static function isLatin(string $text): string
    {
        return preg_match('/^[a-zA-Z0-9\s.,!?\'"()]+$/', $text);
    }

    /**
     * Конвертирует продолжительность из десятичных минут в формат m:ss (например, 2.6 -> "2:36")
     */
    public static function durationDecimalToMmSs(?float $decimal): ?string
    {
        if ($decimal === null || $decimal < 0) {
            return null;
        }
        $minutes = (int) floor($decimal);
        $seconds = (int) round(($decimal - $minutes) * 60);
        if ($seconds >= 60) {
            $minutes += 1;
            $seconds = 0;
        }
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Конвертирует продолжительность из формата m:ss в десятичные минуты (например, "2:36" -> 2.6)
     */
    public static function durationMmSsToDecimal(mixed $mmSs): ?float
    {
        $mmSs = $mmSs === null ? '' : trim((string) $mmSs);
        if ($mmSs === '') {
            return null;
        }
        if (!preg_match('/^(\d+):([0-5]?\d)$/', $mmSs, $m)) {
            return null;
        }
        $minutes = (int) $m[1];
        $seconds = (int) $m[2];
        return $minutes + $seconds / 60;
    }
}
