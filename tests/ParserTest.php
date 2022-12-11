<?php

namespace App\Tests;

use App\Config;
use App\Exporter;
use App\Importer;
use App\Item;
use App\Model\Day;
use App\Model\EmptyLine;
use App\Model\Issue;
use App\Model\Minutes;
use App\Model\Node;
use App\Model\Pause;
use App\Model\Time;
use App\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{

    /** @test */
    public function should_read_file()
    {
        $parser = new Parser(new Config('test.config'));
        $root = $parser->parse(
            $in =
                <<<TEXT

11.10.2022

08:00
TEST-10 Review
08:15
TEST-10 Review
foo x
08:30


TEXT
        );

        $this->assertEquals(
            (new Node())
                ->addChild(new EmptyLine())
                ->addChild(
                    (new Day('11.10.2022'))
                        ->addChild(new EmptyLine())
                        ->addChild((new Time('08:00'))->addChild(new Issue('TEST-10 Review', false)))
                        ->addChild(
                            (new Time('08:15'))
                                ->addChild(new Issue('TEST-10 Review', false))
                                ->addChild(new Issue('foo', true))
                        )
                        ->addChild(new Time('08:30'))
                        ->addChild(new EmptyLine())
                        ->addChild(new EmptyLine())
                ),
            $root
        );

        $out = $root->__toString();
        $this->assertEquals(str_replace("\r\n", "\n", $in."\n"), $out);
    }

    /** @test */
    public function should_read_children()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            $in =
                <<<TEXT
11.10.2022

08:00
TEST-10 Review
08:05 5m TEST-11 Do other stuff
08:10 5m x
08:20

TEXT
        );

        $this->assertEquals(
            (new Node())
                ->addChild(
                    (new Day('11.10.2022'))
                        ->addChild(new EmptyLine())
                        ->addChild(
                            (new Time('08:00'))
                                ->addChild(new Issue('TEST-10 Review', false))
                                ->addChild(
                                    (new Time('08:05'))->addChild(
                                        (new Minutes(5))->addChild(new Issue('TEST-11 Do other stuff', false))
                                    )
                                )
                                ->addChild((new Time('08:10'))->addChild((new Minutes(5))->addChild(new Pause(true))))
                        )
                        ->addChild(new Time('08:20'))
                        ->addChild(new EmptyLine())
                ),
            $root
        );

        $out = $root->__toString();
        $this->assertEquals(str_replace("\r\n", "\n", $in."\n"), $out);
    }

    /** @test */
    public function should_read_standalone_tasks()
    {
        $parser = new Parser(new Config('test.config'));
        $root = $parser->parse(
            $in =
                <<<TEXT
11.10.2022

08:05 5m TEST-11 Do other stuff
08:15 10m


TEXT
        );

        $this->assertEquals(
            (new Node())
                ->addChild(
                    (new Day('11.10.2022'))
                        ->addChild(new EmptyLine())
                        ->addChild(
                            (new Time('08:05'))->addChild(
                                (new Minutes(5))->addChild(new Issue('TEST-11 Do other stuff', false))
                            )
                        )
                        ->addChild((new Time('08:15'))->addChild((new Minutes(10))->addChild(new Pause(false))))
                        ->addChild(new EmptyLine())
                        ->addChild(new EmptyLine())
                ),
            $root
        );

        $out = $root->__toString();
        $this->assertEquals(str_replace("\r\n", "\n", $in."\n"), $out);
    }

}
