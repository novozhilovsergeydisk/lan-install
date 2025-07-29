<?php

namespace App\Helpers;

class StringHelper
{
    /**
     * Сокращает полное имя до фамилии и инициала
     * 
     * @param string|null $fullName Полное имя
     * @return string Сокращенное имя
     */
    public static function shortenName(?string $fullName): string
    {
        if (empty($fullName)) {
            return '';
        }
        
        $parts = explode(' ', $fullName);
        if (count($parts) < 2) {
            return $fullName;
        }
        
        $lastName = $parts[0];
        $firstName = $parts[1];
        
        return $lastName . ' ' . mb_substr($firstName, 0, 1) . '.';
    }
}
