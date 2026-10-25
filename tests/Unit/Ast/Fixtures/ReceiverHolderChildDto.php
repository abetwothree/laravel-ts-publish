<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Redeclares `$held` as the child DTO and fills it so, beside a public namesake of the parent's private `$own`. */
final class ReceiverHolderChildDto extends ReceiverHolderBaseDto
{
    public ReceiverChildDto $own;

    /** @var ReceiverChildDto */
    protected $held;

    /** Fill `$held` as this class redeclares it, and its own `$own`. */
    public function __construct()
    {
        parent::__construct();
        $this->held = new ReceiverChildDto;
        $this->own = new ReceiverChildDto;
    }
}
