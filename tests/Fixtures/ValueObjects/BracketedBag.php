<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** Returns types a docblock spells from a bracket, conditional or not, and conditionals a body may or may not prove. */
final class BracketedBag
{
    /**
     * @param  list<int|string>  $items
     */
    public function __construct(public array $items) {}

    /**
     * Lists every item.
     *
     * @return (int|string)[]
     */
    public function mixedIds()
    {
        return $this->items;
    }

    /**
     * The first item, if any.
     *
     * @return (int|string)|null
     */
    public function maybeOne()
    {
        return $this->items[0] ?? null;
    }

    /**
     * A maker it never has.
     *
     * @return (callable(): int)|null
     */
    public function maker()
    {
        return null;
    }

    /**
     * One or the other, which the body proves.
     *
     * @return ($flag is true ? int : string)
     */
    public function proven(bool $flag = true)
    {
        return $flag ? 1 : 'a';
    }

    /**
     * One or the other, through a call the body cannot type.
     *
     * @return ($flag is true ? int : string)
     */
    public function opaque(bool $flag = true)
    {
        return $this->first();
    }

    /**
     * One of two words, every arm a string.
     *
     * @return ($flag is true ? 'yes' : 'no')
     */
    public function answer(bool $flag = true)
    {
        return $flag ? 'yes' : 'no';
    }

    /**
     * One item or a list of them, an arm of which names no type, through a call the body cannot type.
     *
     * @return ($number is null ? mixed : list<mixed>)
     */
    public function guessed(?int $number = null)
    {
        return $this->first();
    }

    /**
     * The first item, untyped.
     */
    public function first()
    {
        return $this->items[0];
    }
}
