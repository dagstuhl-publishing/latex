<?php

namespace Dagstuhl\Tests\Latex;

use Dagstuhl\Latex\Strings\MetadataString;
use Dagstuhl\Latex\Utilities\Filesystem;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    /**
     * @dataProvider conversionProvider
     * @param string $originalString
     * @param string $result
     */
    public function testConversion(string $originalString, string $result)
    {
        $string = new MetadataString($originalString);
        $this->assertSame($result, $string->toUtf8String());
        // $calculatedResult = $string->normalizeMacro();
        // $this->assertSame($result, $calculatedResult->getString());
    }

    public function conversionProvider(): array
    {
        $testData = [];

        $list = Filesystem::get(__DIR__.'/data/latex-to-utf8-names.txt');

        $lines = explode("\n", $list);

        foreach($lines as $line) {

            if (!str_starts_with($line, '#')) {
                $testData[] = explode(';', $line);
            }

        }

        return $testData;
    }

}
