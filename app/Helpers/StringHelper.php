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

    /**
     * Формирует превью комментария из первых N слов и экранирует его для безопасного вывода.
     * Возвращает массив: 'html' — уже ЭКРАНИРОВАННЫЙ текст (можно выводить через {!! !!}),
     * 'ellipsis' — строка с многоточием ("..." либо пустая строка).
     *
     * @param string|null $comment
     * @param int $wordLimit
     * @return array{html:string, ellipsis:string}
     */
    public static function makeEscapedPreview(?string $comment, int $wordLimit = 4): array
    {
        $comment = $comment ?? '';
        // Декодируем сущности на входе (если текст уже содержит &lt; и т.п.)
        $decoded = htmlspecialchars_decode($comment);
        // Превращаем <br> в пробелы для единого подсчёта слов
        $normalized = preg_replace('/<br\s*\/?>(\s)*/i', ' ', $decoded);
        // Удаляем теги (превью текстовое, без HTML)
        $textOnly = strip_tags((string) $normalized);
        $textOnly = trim($textOnly);

        $words = $textOnly === '' ? [] : preg_split('/\s+/u', $textOnly);
        if (!is_array($words)) {
            $words = [];
        }
        $limit = max(0, $wordLimit);
        $snippetWords = array_slice($words, 0, $limit);
        $needsEllipsis = count($words) > $limit;
        $snippetText = implode(' ', $snippetWords);

        // Линкуем URL'ы безопасно: экранируем НЕ-URL части, а для URL создаём <a>
        $pattern = '/((https?:\/\/|www\.)[^\s<]+)/iu';
        $result = '';
        $offset = 0;
        if (preg_match_all($pattern, $snippetText, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $idx => $m) {
                $url = $m[0];
                $start = $m[1];
                // Добавляем экранированный текст до URL
                $result .= e(substr($snippetText, $offset, $start - $offset));
                // Формируем безопасный href
                $href = stripos($url, 'http') === 0 ? $url : ('http://' . $url);
                $result .= '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer">' . e($url) . '</a>';
                $offset = $start + strlen($url);
            }
            // Хвост после последнего URL
            $result .= e(substr($snippetText, $offset));
        } else {
            $result = e($snippetText);
        }

        return [
            'html' => $result,
            'ellipsis' => $needsEllipsis ? '...' : '',
        ];
    }
}
