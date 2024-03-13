<?php

require __DIR__ . '/../vendor/autoload.php';

use Dagstuhl\Tests\Latex\LatexCommandTest;
use Dagstuhl\Tests\Latex\LatexMacrosAndEnvironmentsTest;

die('script is locked - Please unlock manually, if you want to overwrite expected test data.');

LatexCommandTest::overwriteExpectedData();
LatexMacrosAndEnvironmentsTest::overwriteExpectedData();

exit();