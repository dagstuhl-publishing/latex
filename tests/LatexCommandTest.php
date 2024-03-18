<?php

namespace Dagstuhl\Latex\Tests;

use Dagstuhl\Latex\LatexStructures\LatexFile;
use Dagstuhl\Latex\Utilities\Filesystem;
use PHPUnit\Framework\TestCase;

class LatexCommandTest extends TestCase
{
    const EXPECTED_DATA_FILE = 'latex-commands-test.json';
    const RAW_DATA_FILE = 'latex-commands-test.tex';

    private static ?array $testData = NULL;

    /**
     * @dataProvider commandsDataProvider
     */
    public function testCommandsParser(array $parsedMacro, array $expectedMacro)
    {
        $this->assertSame($parsedMacro, $expectedMacro);
    }

    public function commandsDataProvider(): array
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
            return static::$testData;
        }

        $file = __DIR__.'/../resources/latex-examples/latex-sample-files/' . static::RAW_DATA_FILE;

        $latexFile = new LatexFile($file);

        $macrosReadFromLatexFile = [];

        foreach($latexFile->getCommands() as $command) {
            $macrosReadFromLatexFile[] = [
                $command->getSnippet(),
                $command->getName(),
                $command->getType(),
                $command->getDeclaration()
            ];
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