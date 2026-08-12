<?php

declare(strict_types=1);

namespace Support\Enums;

enum RenameSnippetResult
{
    case Renamed;
    case Missing;
    case Conflict;
}
