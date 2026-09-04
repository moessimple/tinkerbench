<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

enum FeedItemKind: string
{
    case Dump = 'dump';
    case Query = 'query';
    case Log = 'log';
    case NPlusOne = 'n_plus_one';
    case Exception = 'exception';
    case Result = 'result';
}
