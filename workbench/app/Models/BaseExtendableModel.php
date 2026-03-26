<?php

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Database\Eloquent\Model;

#[TsExtends('ParentModelInterface', '@/types/model-parent')]
class BaseExtendableModel extends Model {}
