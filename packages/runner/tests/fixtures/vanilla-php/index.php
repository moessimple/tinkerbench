<?php

// A plain PHP site with no Composer, served by Herd. There is no autoloader; each file is
// require'd explicitly.
require __DIR__.'/src/Calculator.php';

echo (new VanillaPhp\Calculator())->add(1, 1);
