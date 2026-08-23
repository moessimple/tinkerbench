<?php

declare(strict_types=1);

namespace App\Enums;

enum DeleteSnippetResult
{
    case Deleted;
    case Missing;
    case Failed;
}
