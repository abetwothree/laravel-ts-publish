<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\IconShade;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\UiTone;

/**
 * Pages whose props are cases of enums with an override, bare, listed or in a constant: an enum's own override, and
 * one beside an interface's.
 */
class UiTonePageController
{
    public const array PALETTE = ['main' => UiTone::Bright, 'muted' => [UiTone::Dim]];

    /**
     * Renders an overridden enum's case.
     */
    public function index(): Response
    {
        return Inertia::render('Ui/Tone', ['tone' => UiTone::Bright, 'tones' => [UiTone::Bright, UiTone::Dim]]);
    }

    /**
     * Renders a constant holding an overridden enum's cases.
     */
    public function palette(): Response
    {
        return Inertia::render('Ui/Palette', ['palette' => self::PALETTE]);
    }

    /**
     * Renders a case of an enum that keeps its own override over its interface's.
     */
    public function shade(): Response
    {
        return Inertia::render('Ui/Shade', ['shade' => IconShade::Light]);
    }
}
