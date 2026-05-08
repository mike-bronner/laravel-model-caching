<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Fixtures;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;

class AuthorExtendingGenerated extends GeneratedAuthor
{
    use Cachable;
}
