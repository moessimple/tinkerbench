<?php

declare(strict_types=1);

namespace App\Enums;

enum RenameSnippetResult
{
    case Renamed;
    case Missing;
    case Conflict;
}
