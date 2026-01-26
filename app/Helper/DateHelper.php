<?php

namespace App\Helper;


class DateHelper
{
    public const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public static function getMonthYear($date)
    {
        $date = \Carbon\Carbon::parse($date);

        return sprintf('%s %d', self::MONTHS[$date->format('m')], $date->format('Y'));
    }
}