<?php

namespace App\Tests;

use App\Exporter;
use App\Importer;
use App\Item;
use App\JiraClient;
use PHPUnit\Framework\TestCase;

final class ImporterTest extends TestCase
{
    
    /** @test */
    public function should_read_file()
    {
        $importer = new Importer();
        $days = $importer->parse(
            $in =
                <<<TEXT
11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
TEST-10 Review
08:15
TEST-10 Review
08:30


TEXT
        );

        $this->assertCount(1, $days);
        $this->assertEquals([
            '11.10.2022' => [
                new Item('TEST-10', ['Review'], '08:00', '08:15'),
                new Item('TEST-10', ['Review'], '08:15', '08:30'),
            ],
        ], $days);

        $out = $importer->render($days);
        $this->assertEquals(str_replace("\r\n", "\n", $in), $out);
    }

    /** @test */
    public function should_detect_pause()
    {
        $importer = new Importer();
        $days = $importer->parse(
            $in =
                <<<TEXT
11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
TEST-10 Review
08:15

08:30
TEST-10 Review
08:45

09:30
TEST-12 Foo
10:45

11:30
TEST-12 baa
12:45


TEXT
        );

        $this->assertCount(1, $days);
        $this->assertEquals([
            '11.10.2022' => [
                new Item('TEST-10', ['Review'], '08:00', '08:15'),
                new Item(null, [''], '08:15', '08:30'),
                new Item('TEST-10', ['Review'], '08:30', '08:45'),
                new Item(null, [''], '08:45', '09:30'),
                new Item('TEST-12', ['Foo'], '09:30', '10:45'),
                new Item(null, [''], '10:45', '11:30'),
                new Item('TEST-12', ['baa'], '11:30', '12:45'),
            ],
        ], $days);
    }

    /** @test */
    public function should_detect_aliases()
    {
        $importer = new Importer();
        $days = $importer->parse(
            $in =
                <<<TEXT
TEST-10 as foo

11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
foo Review
08:15

08:30
foo Review 2
08:45

TEXT
        );

        $this->assertEquals(['foo' => 'TEST-10'], $importer->getAliases());
        $this->assertCount(1, $days);
        $this->assertEquals([
            '11.10.2022' => [
                new Item('TEST-10 ( foo )', ['Review'], '08:00', '08:15'),
                new Item(null, [''], '08:15', '08:30'),
                new Item('TEST-10 ( foo )', ['Review 2'], '08:30', '08:45'),
            ],
        ], $days);
    }

    /** @test */
    public function should_detect_no_issue()
    {
        $importer = new Importer();
        $days = $importer->parse(
            $in =
                <<<TEXT
11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
No issue
08:15


TEXT
        );

        $this->assertCount(1, $days);
        $this->assertEquals([
            '11.10.2022' => [
                new Item(null, ['No issue'], '08:00', '08:15'),
            ],
        ], $days);
    }

    /** @test */
    public function should_sanitize()
    {
        $importer = new Importer();
        $days = $importer->parse(
            $in =
                <<<TEXT
TEST-10 as Test
11.10.2022 ++++++++++++++++++++++++

08:00
Test Review
08:15

08:30
Test Review
08:45

09:30
10:30
10:45
TEXT
        );
        $importer->diff($in);

        $out =
            <<<TEXT
TEST-10 as Test

11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
Test Review
08:15

08:30
Test Review
08:45

09:30
10:30
10:45


TEXT;
        $this->assertEquals(str_replace("\r\n", "\n", $out), $importer->render($days));
    }

    /** @test */
    public function should_be_stable()
    {
        $importer = new Importer();
        $days = $importer->parse(
            $in =
                <<<TEXT
TEST-10 as Test

11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
Test Review
08:15

08:30
Test Review
08:45

09:30
10:30
10:45


TEXT
        );
        $importer->diff($in);

        $this->assertEquals(str_replace("\r\n", "\n", $in), $importer->render($days));
    }

}
