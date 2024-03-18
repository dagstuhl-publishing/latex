<?php

namespace Dagstuhl\Latex\Tests;

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;
use PHPUnit\Framework\TestCase;

class LatexMacrosAndEnvironmentsTest extends TestCase
{
    const EXPECTED_DATA_FILE = 'latex-macros-and-environments-test.json';
    const RAW_DATA_FILE = 'latex-macros-and-environments-test.tex';

    private static ?array $testData = NULL;

    /**
     * @dataProvider macrosAndEnvironmentsDataProvider
     */
    public function testMacrosAndEnvironmentsParser(array $parsedMacro, array $expectedMacro)
    {
        $this->assertSame($parsedMacro, $expectedMacro);
    }

    public function macrosAndEnvironmentsDataProvider(): array
    {
        $expectedMacros = json_decode(Filesystem::get(__DIR__.'/data/'.static::EXPECTED_DATA_FILE));

        $data = [];

        foreach(static::getTestData() as $key=>$macro) {
            $data[] = [ $macro, $expectedMacros[$key] ];
        }

        return $data;
    }

    public static function getTestData(): array
    {
        if (static::$testData !== NULL) {
            return self::$testData;
        }

        $file = __DIR__.'/../resources/latex-examples/latex-sample-files/' . static::RAW_DATA_FILE;

        $latexFile = new LatexFile($file);

        $macrosReadFromLatexFile = [];

        $macroNames = [ 'documentclass', 'title', 'author', 'relatedversiondetails', 'supplementdetails' ];

        foreach($macroNames as $name) {
            foreach ($latexFile->getMacros($name) as $macro) {
                $macrosReadFromLatexFile[] = [
                    $macro->getSnippet(),
                    $macro->getName(),
                    $macro->getArgument(),
                    $macro->getArguments(),
                    $macro->getOptions()
                ];
            }
        }

        static::$testData = $macrosReadFromLatexFile;

        return static::$testData;
    }

    public static function overwriteExpectedData(): void
    {
        $macros = static::getTestData();

        Filesystem::put(__DIR__ . '/data/'.static::EXPECTED_DATA_FILE, json_encode($macros, JSON_PRETTY_PRINT));
    }
}