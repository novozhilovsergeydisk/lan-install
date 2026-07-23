<?php

namespace Tests\Unit;

use App\Console\Commands\CreateRequestArchive;
use Tests\TestCase;

/**
 * "Скачать zip архив всех фото" (CommentPhotoController::downloadAllPhotos ->
 * artisan archive:create -> CreateRequestArchive) использует свой собственный
 * whitelist-фильтр имён папок, отдельный от PhotoReportController::sanitizeFolderName.
 * "*" не входил в список разрешённых символов, заменялся на пробел и терялся
 * после trim() — папка "113*" превращалась в чистое "113".
 */
class RequestArchiveFolderNameTest extends TestCase
{
    private function buildCommentFolderName(string $text): string
    {
        $command = new CreateRequestArchive();
        $method = new \ReflectionMethod($command, 'buildCommentFolderName');
        $method->setAccessible(true);

        return $method->invoke($command, $text);
    }

    public function test_asterisk_is_replaced_with_fullwidth_lookalike_not_removed()
    {
        $this->assertSame('113＊', $this->buildCommentFolderName('113*'));
        $this->assertStringNotContainsString('*', $this->buildCommentFolderName('113*'));
    }

    public function test_asterisk_preserved_within_longer_comment()
    {
        $result = $this->buildCommentFolderName('101* не работает розетка 220 в');
        $this->assertSame('101＊ не работает розетка 220 в', $result);
    }

    public function test_plain_text_without_special_characters_unchanged()
    {
        $this->assertSame('116', $this->buildCommentFolderName('116'));
    }
}
