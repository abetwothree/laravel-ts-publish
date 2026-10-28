<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Gadget;
use Inertia\Inertia;
use Inertia\Response;

class UiGadgetPageController
{
    /**
     * A page whose prop is a value of a composed class.
     */
    public function show(): Response
    {
        return Inertia::render('Ui/Gadget', ['gadget' => $this->gadget()]);
    }

    /**
     * A gadget, by its declared class.
     */
    protected function gadget(): Gadget
    {
        return new Gadget;
    }
}
