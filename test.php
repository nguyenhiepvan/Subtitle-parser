<?php
/**
 * Created by PhpStorm.
 * User: Hiệp Nguyễn
 * Date: 16/06/2022
 * Time: 17:14
 */


use Nguyenhiep\SubtitleParser\Conversation;

require_once __DIR__ . '/vendor/autoload.php';

$vie = Conversation::parse(file_get_contents(__DIR__ . '/Sample/vi.srt'), "vi");
$eng = Conversation::parse(file_get_contents(__DIR__ . '/Sample/en.srt'), "en");
$chi = Conversation::parse(file_get_contents(__DIR__ . '/Sample/chi.srt'), "chi");
$eng->compare($vie);
$eng->compare($chi);

foreach ($eng->getIterator() as $subtitle) {
    file_put_contents('result_' . time() . '.txt', $subtitle->__toString() . PHP_EOL, FILE_APPEND | LOCK_EX);
}

