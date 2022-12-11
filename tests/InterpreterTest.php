<?php

namespace App\Tests;

use App\Config;
use App\Exporter;
use App\Interactor;
use App\Interpreter;
use App\Item;
use App\JiraClient;
use App\Model\Issue;
use App\Model\Log;
use App\Parser;
use DateTime;
use PHPUnit\Framework\TestCase;

final class InterpreterTest extends TestCase
{

    private Interpreter $interpreter;

    protected function setUp(): void
    {
        $this->interpreter = new Interpreter();
    }

    /** @test */
    public function should_resolve_alias()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            $in =
                <<<TEXT

TEST-1 as GlobalAlias 
11.10.2022
TEST-2 as LocalAlias
07:15 5m TEST-2 Some Issue
07:30 5m GlobalAlias Some Issue

12.10.2022
OVERWRITE-1 as GlobalAlias
07:15 5m LocalAlias Some Issue
07:30 5m GlobalAlias Some Issue
07:45 10m OVERWRITE-1 Some Issue

TEXT
        );

        $logs = $this->interpreter->getLogs($root);
        $issues = $root->getIssues();

        $this->assertEquals([
            new Log(new DateTime('11.10.2022 07:15'), [$issues[0]], new DateTime('11.10.2022 07:20')),
            new Log(new DateTime('11.10.2022 07:30'), [$issues[1]], new DateTime('11.10.2022 07:35')),
            new Log(new DateTime('12.10.2022 07:15'), [$issues[2]], new DateTime('12.10.2022 07:20')),
            new Log(new DateTime('12.10.2022 07:30'), [$issues[3]], new DateTime('12.10.2022 07:35')),
            new Log(new DateTime('12.10.2022 07:45'), [$issues[4]], new DateTime('12.10.2022 07:55')),
        ], $logs);

        $this->assertEquals('TEST-2', $issues[0]->issue);
        $this->assertEquals('LocalAlias', $issues[0]->alias);
        $this->assertEquals('TEST-1', $issues[1]->issue);
        $this->assertEquals('GlobalAlias', $issues[1]->alias);
        $this->assertEquals('LocalAlias', $issues[2]->issue);
        $this->assertEquals('LocalAlias', $issues[2]->alias);
        $this->assertEquals('OVERWRITE-1', $issues[3]->issue);
        $this->assertEquals('GlobalAlias', $issues[3]->alias);
        $this->assertEquals('OVERWRITE-1', $issues[4]->issue);
        $this->assertEquals('GlobalAlias', $issues[4]->alias);
    }

}
