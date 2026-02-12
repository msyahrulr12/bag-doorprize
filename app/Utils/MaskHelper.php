<?php

namespace App\Utils;

class MaskHelper
{
    /**
     * Mask a name (e.g., John Doe -> J*** Doe)
     * Or simpler: J*** D**
     */
    public static function name(?string $name): string
    {
        if (!$name)
            return 'N/A';

        $words = explode(' ', $name);
        $maskedWords = array_map(function ($word) {
            $len = strlen($word);
            if ($len <= 1)
                return $word;
            if ($len <= 2)
                return substr($word, 0, 1) . '*';
            return substr($word, 0, 1) . str_repeat('*', $len - 2) . substr($word, -1);
        }, $words);

        return implode(' ', $maskedWords);
    }

    /**
     * Mask a CIF or account number (e.g., 12345678 -> 12****78)
     */
    public static function mask(?string $value, int $visibleStart = 2, int $visibleEnd = 2): string
    {
        if (!$value)
            return 'N/A';

        $len = strlen($value);
        if ($len <= ($visibleStart + $visibleEnd)) {
            return $value;
        }

        return substr($value, 0, $visibleStart) . str_repeat('*', $len - ($visibleStart + $visibleEnd)) . substr($value, -$visibleEnd);
    }

    /**
     * Mask an email
     */
    public static function email(?string $email): string
    {
        if (!$email)
            return 'N/A';

        $parts = explode('@', $email);
        if (count($parts) < 2)
            return self::mask($email);

        $name = $parts[0];
        $domain = $parts[1];

        return self::mask($name, 2, 1) . '@' . $domain;
    }
}
