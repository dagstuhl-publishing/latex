<?php

require __DIR__ . '/../vendor/autoload.php';

use Dagstuhl\Latex\Tests\LatexCommandTest;
use Dagstuhl\Latex\Tests\LatexMacrosAndEnvironmentsTest;

\Dagstuhl\Latex\LatexStructures\LatexString::$useParser = false;

die('script is locked - Please unlock manually, if you want to overwrite expected test data.');

LatexCommandTest::overwriteExpectedData();

LatexMacrosAndEnvironmentsTest::overwriteExpectedData();

exit();