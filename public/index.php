<?php

declare(strict_types=1);

use Framework\Http\Request;

require __DIR__ . '/../bootstrap/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$app->run(Request::fromGlobals());
