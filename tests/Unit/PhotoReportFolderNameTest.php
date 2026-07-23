<?php

namespace Tests\Unit;

use App\Http\Controllers\PhotoReportController;
use Tests\TestCase;

/**
 * Монтажники используют "*" в комментариях как метку "важно" (напр. "113*").
 * Windows запрещает "*" в именах файлов/папок, поэтому при распаковке zip-архива
 * фотоотчёта звёздочка терялась. Заменяем на полноширинный аналог "＊" (другой
 * символ юникода, не входит в список запрещённых), чтобы метка сохранялась визуально.
 */
class PhotoReportFolderNameTest extends TestCase
{
    private function sanitizeFolderName(string $name): string
    {
        $controller = new PhotoReportController();
        $method = new \ReflectionMethod($controller, 'sanitizeFolderName');
        $method->setAccessible(true);

        return $method->invoke($controller, $name);
    }

    public function test_asterisk_is_replaced_with_fullwidth_lookalike_not_removed()
    {
        $this->assertSame('113＊', $this->sanitizeFolderName('113*'));
        $this->assertStringNotContainsString('*', $this->sanitizeFolderName('113*'));
    }

    public function test_asterisk_preserved_within_longer_comment()
    {
        $result = $this->sanitizeFolderName('101* не работает розетка 220 в');
        $this->assertSame('101＊ не работает розетка 220 в', $result);
    }

    public function test_other_forbidden_windows_characters_still_replaced_with_underscore()
    {
        $result = $this->sanitizeFolderName('каб:104/105?"<>|');
        $this->assertSame('каб_104_105_____', $result);
        $this->assertStringNotContainsString(':', $result);
        $this->assertStringNotContainsString('/', $result);
    }

    public function test_plain_text_without_special_characters_unchanged()
    {
        $this->assertSame('108( мобильная стойка )', $this->sanitizeFolderName('108( мобильная стойка )'));
    }
}
