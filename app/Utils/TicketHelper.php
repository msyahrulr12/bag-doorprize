<?php

namespace App\Utils;

class TicketHelper
{
    public static function format(int $number): string
    {
        return chr(65 + intdiv($number, 99999999)) .
            str_pad($number % 99999999 + 1, 8, '0', STR_PAD_LEFT);
    }

    public static function parse(string $ticket): int
    {
        $prefix = substr($ticket, 0, 1);
        $numberPart = (int) substr($ticket, 1);

        $multiplier = ord($prefix) - 65;
        return ($multiplier * 99999999) + ($numberPart - 1);
    }
}
