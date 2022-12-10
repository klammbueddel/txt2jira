<?php

namespace App;

use DateInterval;
use DateTimeImmutable;

class JiraDateInterval extends DateInterval
{

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
