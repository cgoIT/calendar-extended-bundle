<?php

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Class_\RenameAttributeRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->skip([
        RenameAttributeRector::class => [
            '*/Controller/Route/'
        ],
    ]);
};
