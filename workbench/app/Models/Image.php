<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
}
