<?php

declare(strict_types=1);

namespace App\Enums;

enum CreateSnippetResult
{
    case Created;
    case Conflict;
    case Failed;
}
