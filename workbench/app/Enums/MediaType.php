<?php

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

/**
 * String-backed enum with both instance and static methods.
 */
enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Archive = 'archive';

    #[TsEnumMethod(description: 'Allowed file extensions for this media type')]
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            self::Video => ['mp4', 'webm', 'mov', 'avi'],
            self::Audio => ['mp3', 'wav', 'ogg', 'flac'],
            self::Document => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
            self::Archive => ['zip', 'tar', 'gz', 'rar', '7z'],
        };
    }

    #[TsEnumMethod(description: 'Maximum file size in MB')]
    public function maxSizeMb(): int
    {
        return match ($this) {
            self::Image => 10,
            self::Video => 500,
            self::Audio => 50,
            self::Document => 25,
            self::Archive => 100,
        };
    }

    #[TsEnumMethod(description: 'Icon name')]
    public function icon(): string
    {
        return match ($this) {
            self::Image => 'photo',
            self::Video => 'film',
            self::Audio => 'musical-note',
            self::Document => 'document-text',
            self::Archive => 'archive-box',
        };
    }

    #[TsEnumStaticMethod(description: 'Get the MIME type prefixes')]
    public static function mimePrefixes(): array
    {
        return [
            'image' => 'image/',
            'video' => 'video/',
            'audio' => 'audio/',
            'document' => 'application/',
            'archive' => 'application/',
        ];
    }
}
