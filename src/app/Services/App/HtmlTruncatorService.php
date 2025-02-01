<?php

namespace App\Services\App;

use DOMDocument;
use Illuminate\Support\Str;

class HtmlTruncatorService
{

    /**
     * Обрезаем контент до нужного кол-ва символов, при этом не учитываем HTML разметку
     * @param $html
     * @param $limit
     * @return string
     */
    public function truncateTextInHtml($html, $limit)
    {
        // Формируем два массива, один с текстом без тегов, второй с тегами
        $joinHtmlItem = $this->parseHtml($html);

        // Формируем второй массив без тегов
        foreach ($joinHtmlItem as $k => $joinHtmlItemItem){
            $arPreviewText[$k] = strip_tags($joinHtmlItemItem);
        }

        $resultHtml = $this->generateLimitedHtml($arPreviewText, $joinHtmlItem, $limit);

//        $resultHtml = '<p></p><table class="w-full" style="min-width:75px;"><tbody><tr><td colspan="1" rowspan="1"><p>Moi je m\'appelle Lolita</td><td colspan="1" rowspan="1"><p>Меня зовут Лолита</td><td colspan="1" rowspan="1"><p>Муа жё мапэль Лолита</td></tr><tr><td colspan="1" rowspan="1"><p>Lo ou bien Lola</td><td colspan="1" rowspan="1"><p>Ло или Лола</td><td colspan="1" rowspan="1"><p>Ло у бьян: Лола</td></tr><tr><td colspan="1" rowspan="1"><p>Du pareil au même</td><td colspan="1" rowspan="1"><p>Это одно и тоже</td><td colspan="1" rowspan="1"><p>Дю парэй о мэм(ё)</td></tr><tr><td colspan="1" rowspan="1"><p>Moi je m\'appelle Lolita</td><td colspan="1" rowspan="1"><p>Меня зовут Лолита</td><td colspan="1" rowspan="1"><p>Муа жё мап...';

        // Закрываем открытые теги
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML(mb_convert_encoding($resultHtml, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        $doc->saveHTML();
        $body = $doc->getElementsByTagName('body')->item(0);
        $innerHtml = '';
        foreach ($body->childNodes as $child) {
            $innerHtml .= $doc->saveHTML($child);
        }
        return $innerHtml;
    }

    /**
     * Разбивает HTML на массив элементов внутри которых есть текст
     * @param $html
     * @return string[]
     */
    private function parseHtml(string $html): array
    {
        $joinHtmlItems = [''];
        $arHtml = explode('>', $html);

        foreach ($arHtml as $k => $htmlItem) {
            if (empty($htmlItem)) {
                continue;
            }

            $htmlItem .= '>';
            $joinHtmlItems[count($joinHtmlItems) - 1] .= $htmlItem;

            if (!$this->isTag($htmlItem)) {
                $joinHtmlItems[] = ''; // Создаем новый элемент для следующего текста
            }
        }
        return $joinHtmlItems;
    }

    /**
     * Проверяет является ли строка тегом
     * @param $string
     * @return bool
     */
    private function isTag(string $string) : bool
    {
        $pattern = '/^<[^>]*>$/';
        return preg_match($pattern, $string) === 1;
    }

    /**
     * Из двух массивов формируем лимитированный (обрезанный) HTML
     * @param $arPreviewText
     * @param $joinHtmlItem
     * @param $limit
     * @return string
     */
    private function generateLimitedHtml(array $arPreviewText, array $joinHtmlItem, int $limit): string
    {
        $resultHtml = '';
        $plainTextLength = 0;

        foreach ($arPreviewText as $k => $previewTextItem) {
            if (Str::contains($joinHtmlItem[$k], $previewTextItem)) {
                $previewTextItemLength = mb_strlen($previewTextItem);
                $plainTextLength += $previewTextItemLength;

                if ($plainTextLength > $limit) {
                    $remains = $plainTextLength - $limit;
                    $previewTextItemMaxLength = $previewTextItemLength - $remains;
                    $posPreviewText = Str::position($joinHtmlItem[$k], $previewTextItem) + $previewTextItemMaxLength;
                    $resultHtml .= Str::limit($joinHtmlItem[$k], $posPreviewText);
                    break;
                }

                $posPreviewText = Str::position($joinHtmlItem[$k], $previewTextItem) + $previewTextItemLength;
                $resultHtml .= mb_substr($joinHtmlItem[$k], 0, $posPreviewText);
            }
        }

        return $resultHtml;
    }
}
