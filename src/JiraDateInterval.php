<?php

namespace App;

use DateInterval;
use DateTimeImmutable;

class JiraDateInterval extends DateInterval
{

    public static function parse(string $input)
    {
        $minutes = 0;
        $tokens = explode(' ', $input);
        foreach($tokens as $token) {
            if (preg_match('/^\+?([0-9\.]+)m$/', $token, $matches)) {
                $minutes += $matches[1];
            }

            if (preg_match('/^\+?([0-9\.]+)h$/', $token, $matches)) {
                $minutes += $matches[1] * 60;
            }

            if (preg_match('/^\+?([0-9\.]+)d$/', $token, $matches)) {
                $minutes += $matches[1] * 60 * 24;
            }
        }

        return new JiraDateInterval('PT'.round($minutes).'M');
    }

    public function getMinutes(): int
    {
        $reference = new DateTimeImmutable;
        $endTime = $reference->add($this);

        return round(($endTime->getTimestamp() - $reference->getTimestamp()) / 60);
    }

    public static function formatMinutes(int $m, $strPad = 7)
    {
        if ($m < 0) {
            return '-'.self::formatMinutes(abs($m), $strPad);
        }
        return str_pad((new JiraDateInterval('PT'.$m.'M'))->__toString(), $strPad, ' ');
    }

    public static function formatSeconds(int $s, $strPad = 7)
    {
        if ($s < 0) {
            return '-'.self::formatSeconds(abs($s), $strPad);
        }
        return str_pad((new JiraDateInterval('PT'.$s.'S'))->__toString(), $strPad, ' ');
    }

    public function __toString(): string
    {
        $reference = new DateTimeImmutable();
        $endTime = $reference->add($this);

        $s = $endTime->getTimestamp() - $reference->getTimestamp();

        $d = floor($s / 3600 / 24);
        $s -= $d * 3600 * 24;
        $h = floor($s / 3600);
        $s -= $h * 3600;
        $m = round($s / 60);

        $items = [];
        if ($d) {
            $items[] = "{$d}d";
        }
        if ($h) {
            $items[] = "{$h}h";
        }
        if ($m) {
            $items[] = "{$m}m";
        }

        return implode(' ', $items);
    }

}
