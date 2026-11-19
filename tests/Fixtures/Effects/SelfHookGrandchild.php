<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A grandchild redeclaring the `tick` get hook, whose `self::clearTick()` runs the reading clearTick() it inherits
 * from SelfHookChild, not SelfHookParent's, which clears the pin.
 */
class SelfHookGrandchild extends SelfHookChild
{
    public bool $tick = true {
        get {
            self::clearTick();

            return $this->tick;
        }
    }
}
