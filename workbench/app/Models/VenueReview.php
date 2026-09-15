<?php

declare(strict_types=1);

namespace Workbench\App\Models;

/** Subclass of Review scoped to venue reviews — shares the reviews table via the inherited $table. */
class VenueReview extends Review {}
