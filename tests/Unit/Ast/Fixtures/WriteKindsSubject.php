<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A model-less subject with one untyped default per kind of node that writes a property, and one nothing writes. */
class WriteKindsSubject
{
    protected $untouched = 'kept';

    protected $assigned = 'a';

    protected $compound = 1;

    protected $referenced = 'r';

    protected $preIncremented = 1;

    protected $preDecremented = 1;

    protected $postIncremented = 1;

    protected $postDecremented = 1;

    protected $foreachKey = 'k';

    protected $foreachValue = 'v';

    protected $foreachReference = ['x'];

    protected $unsetOne = 'u';

    protected $pushed = ['p'];

    /** Writes each property through its own kind of node. */
    public function write(array $items): void
    {
        $this->assigned = 'b';
        $this->compound += 2;
        $alias = &$this->referenced;
        $this->preIncremented++;
        $this->preDecremented--;
        $this->postIncremented++;
        $this->postDecremented--;

        foreach ($items as $this->foreachKey => $item) {
            unset($item);
        }

        foreach ($items as $this->foreachValue) {
            continue;
        }

        foreach ($this->foreachReference as &$item) {
            unset($item);
        }

        unset($this->unsetOne);
        array_push($this->pushed, 'q');
    }
}
