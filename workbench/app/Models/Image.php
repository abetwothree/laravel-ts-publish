<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Workbench\App\Casts\MenuSettings;
use Workbench\App\Enums\Status;
use Workbench\App\ValueObjects\ArrayableData;
use Workbench\App\ValueObjects\StringableLabel;

class Image extends Model
{
    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'url',
        'alt_text',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** Polymorphic parent (Product, Post, User, etc.) */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Human-readable file size */
    protected function sizeForHumans(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $bytes = $this->size_bytes;
                $units = ['B', 'KB', 'MB', 'GB'];
                $i = 0;
                while ($bytes >= 1024 && $i < count($units) - 1) {
                    $bytes /= 1024;
                    $i++;
                }

                return round($bytes, 1).' '.$units[$i];
            },
        );
    }

    /** Whether the image is landscape orientation */
    protected function isLandscape(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => ($this->width ?? 0) > ($this->height ?? 0),
        );
    }

    /** Aspect ratio as a string (e.g. "16:9") or null if dimensions not set */
    protected function aspectRatio(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->width || ! $this->height) {
                    return null;
                }
                $gcd = gmp_intval(gmp_gcd($this->width, $this->height));

                return ($this->width / $gcd).':'.($this->height / $gcd);
            },
        );
    }

    /** @return Attribute<string|null, never> */
    protected function extension(): Attribute
    {
        return Attribute::make(
            get: fn () => pathinfo($this->path ?? '', PATHINFO_EXTENSION) ?: null,
        );
    }

    /**
     * This is the size test to parse from the docblock in the test for accessor type resolution.
     *
     * @return Attribute<number, never>
     */
    protected function size(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->size_bytes,
        );
    }

    /** @return Attribute<string|int|null, never> */
    protected function flexibleId(): Attribute
    {
        return Attribute::make(get: fn () => $this->id);
    }

    /** @return Attribute<?string, never> */
    protected function optionalLabel(): Attribute
    {
        return Attribute::make(get: fn () => $this->alt_text ?: null);
    }

    /** @return Attribute<Status|null, never> */
    protected function statusFromDocblock(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    /** @return Attribute<User|null, never> */
    protected function uploaderFromDocblock(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    /** @return Attribute<MenuSettings, never> */
    protected function configFromDocblock(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    /** @return Attribute<ArrayableData, never> */
    protected function dataFromDocblock(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    /** @return Attribute<StringableLabel, never> */
    protected function labelFromDocblock(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    protected function noDocblockAccessor(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    /**
     * @return ?string
     */
    protected function wrongFormatDocblock(): Attribute
    {
        return Attribute::make(get: fn () => '');
    }

    /** @return Attribute<positive-int, never> */
    protected function positiveIntAccessor(): Attribute
    {
        return Attribute::make(get: fn () => $this->size_bytes);
    }

    /** @return Attribute<numeric-string, never> */
    protected function numericStringAccessor(): Attribute
    {
        return Attribute::make(get: fn () => (string) $this->size_bytes);
    }
}
