<?php

namespace App\Services;

class PlayerService
{

    public function formatText(string $text): string
    {
        $arrText = $this->formatTextToArray($text);

        $res = '';
        foreach($arrText as $val){
            $res .= $val."\n";
        }
        return $res;
    }

    public function formatTextToArray(string $text): array
    {
        $arrRes = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            preg_match_all('#\[.*?\]#i', $line, $matches);

            foreach ($matches[0] as $match) {
                $time = $this->timeSec($match);
                // Удаляем все вхождения квадратных скобок из строки
                $formattedLine = preg_replace('#\[.*?\]#iu', "", $line);
                $arrRes[$time] = trim($formattedLine); // Удаляем лишние пробелы
            }
        }

        ksort($arrRes);

        return $arrRes;
    }

    public function getSongNameFromFileName(string $fileName): string
    {
        if(empty($fileName)){
            return '';
        }

        $fileName = str_replace(['_', '-', '.'], ' ', $fileName);

        $fileName = preg_replace("/[^a-zA-Zа-яА-Я0-9\s]/u", '', $fileName);
        $result = preg_replace('/\s+/', ' ', $fileName);

        return $result;
    }

    private function timeSec(string $time): string
    {
        $time = preg_replace("#\[|\]#iu", "", $time);
        $time = explode(':', $time);
        if($time[0] > 0){
            $res = ((int)$time[0] * 60) + $time[1];
        }else{
            $res = $time[1];
        }

        return (string)$res;
    }
}
