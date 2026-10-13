<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/** An abstract model whose string key comes from its parent's #[Table]. */
abstract class AbstractInheritedTableKeyModel extends AbstractTableKeyModel {}
