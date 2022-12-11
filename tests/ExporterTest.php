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

final class ExporterTest extends TestCase
{

    private $interactor;
    private $client;
    private Exporter $exporter;

    protected function setUp(): void
    {
        $this->client = $this->createMock(JiraClient::class);
        $this->interactor = $this->createMock(Interactor::class);
        $this->exporter = new Exporter($this->client, $this->interactor);
    }

    /** @test */
    public function should_flatten_log()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            $in =
                <<<TEXT
11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

07:15 5m TEST-1 Some Issue
07:25 10m TEST-2 Some Issue done x

08:00
TEST-3 Some regular issue
08:15
TEST-4 Some main issue that has a standalone break, 
two standalone issues and another comment where the containing
issue number will be ignored because it is not the first line
08:30 10m
08:45 5m TEST-5 Some child issue after a break
08:55 5m TEST-6 Some other child issue marked as done
TEST-3 Some other log comment regarding current main issue
10:00

TEXT
        );

        $logs = (new Interpreter())->getLogs($root);
        $chargableItems = $this->exporter->chargeableItems($logs);
        $this->assertCount(8, $chargableItems);

        $idx = 0;
        $this->assertEquals('07:15', $chargableItems[$idx]['date']->format('H:i'));
        $this->assertEquals('07:20', $chargableItems[$idx]['end']->format('H:i'));

        $idx++;
        $this->assertEquals('08:00', $chargableItems[$idx]['date']->format('H:i'));
        $this->assertEquals('08:15', $chargableItems[$idx]['end']->format('H:i'));

        $idx++;
        $this->assertEquals('08:15', $chargableItems[$idx]['date']->format('H:i'));
        $this->assertEquals('08:30', $chargableItems[$idx]['end']->format('H:i'));
        $this->assertEquals(
            'Some main issue that has a standalone break,; two standalone issues and another comment '
            .'where the containing; issue number will be ignored because it is not the first line; TEST-3 Some other'
            .' log comment regarding current main issue',
            $chargableItems[$idx]['comment']
        );

        $idx++;
        $this->assertEquals('08:40', $chargableItems[$idx]['date']->format('H:i'));
        $this->assertEquals('08:45', $chargableItems[$idx]['end']->format('H:i'));

        $idx++;
        $this->assertEquals('08:45', $chargableItems[$idx]['date']->format('H:i'));
        $this->assertEquals('08:50', $chargableItems[$idx]['end']->format('H:i'));

        $idx++;
        $this->assertEquals('08:50', $chargableItems[$idx]['date']->format('H:i'));
        $this->assertEquals('08:55', $chargableItems[$idx]['end']->format('H:i'));

        $idx++;
        $this->assertEquals('08:55', $chargableItems[$idx]['date']->format('H:i'));
        $this->assertEquals('09:00', $chargableItems[$idx]['end']->format('H:i'));

        $idx++;
        $this->assertEquals('09:00', $chargableItems[$idx]['date']->format('H:i'));
        $this->assertEquals('10:00', $chargableItems[$idx]['end']->format('H:i'));

        $this->assertEquals('08:15', $logs[3]->start->format('H:i'));
        $this->assertEquals('10:00', $logs[3]->end->format('H:i'));
        $this->assertEquals(85, $logs[3]->getMinutes()); # sum of all chargeable items in the block until 10:00
    }

    /** @test */
    public function should_aggregate()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            $in =
                <<<TEXT
11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

07:15 5m TEST-1 Some Issue x
07:25 10m TEST-2 Some Issue done x

08:00
TEST-1 Some regular issue
08:15
TEST-2 Some main issue
08:30 10m
08:45 5m TEST-1 Some child issue after a break
08:55 5m TEST-2 Some other child issue marked as done x
TEST-3 Some other log comment regarding current main issue
09:15
10:15
TEST-3 Some other log comment
10:30

11:50
Foo
11:55
11:50 10m Foo
TEXT
        );

        $logs = (new Interpreter())->getLogs($root);
        $chargableItems = $this->exporter->aggregateUncommitedLogs($logs);
        $issues = $root->getIssues();

        $this->assertEquals([
            [
                'date' => new DateTime('11.10.2022 08:00'),
                'end' => new DateTime('11.10.2022 08:50'),
                'issue' => 'TEST-1',
                'minutes' => 20,
                'total' => 25,
                'comment' => 'Some regular issue; Some child issue after a break',
                'issues' => [$issues[2], $issues[4]],
                'transient' => false,
            ],
            [
                'date' => new DateTime('11.10.2022 08:15'),
                'end' => new DateTime('11.10.2022 09:15'),
                'issue' => 'TEST-2',
                'minutes' => 40,
                'total' => 55,
                'comment' => 'Some main issue; TEST-3 Some other log comment regarding current main issue',
                'issues' => [$issues[3]],
                'transient' => false,
            ],
            [
                'date' => new DateTime('11.10.2022 10:15'),
                'end' => new DateTime('11.10.2022 10:30'),
                'issue' => 'TEST-3',
                'minutes' => 15,
                'total' => 15,
                'comment' => 'Some other log comment',
                'issues' => [$issues[7]],
                'transient' => false,
            ],
            [
                'date' => new DateTime('11.10.2022 11:50'),
                'end' => new DateTime('11.10.2022 12:00'),
                'issue' => 'Foo',
                'minutes' => 15,
                'total' => 15,
                'comment' => '',
                'issues' => [$issues[8], $issues[9]],
                'transient' => false,
            ],
        ], $chargableItems);
    }

    /** @test */
    public function should_filter_todo()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            $in =
                <<<TEXT
11.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
TEST-10 Already charged x
08:15
TEST-10 Some more content
08:30
TEXT
        );

        $logs = (new Interpreter())->getLogs($root);
        $chargeableItems = $this->exporter->aggregateUncommitedLogs($logs);
        $issues = $root->getIssues();

        $this->assertEquals([
            [
                'date' => new DateTime('11.10.2022 08:15'),
                'end' => new DateTime('11.10.2022 08:30'),
                'issue' => 'TEST-10',
                'minutes' => 15,
                'total' => 30,
                'comment' => 'Some more content',
                'issues' => [$issues[1]],
                'transient' => false,
            ],
        ], $chargeableItems);
    }

    /** @test */
    public function should_ignore_invalid_date()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            <<<TEXT
00.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
TEST-10 Some content
08:45
TEXT
        );

        $logs = (new Interpreter())->getLogs($root);
        $chargeableItems = $this->exporter->aggregateUncommitedLogs($logs);

        $this->assertCount(0, $chargeableItems);
    }

    /** @test */
    public function should_allow_nonsense_standalone_logs()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            $in =
                <<<TEXT
01.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
TEST-10 Some content
08:45
08:30 5m TEST-10 Some content
TEXT
        );

        $logs = (new Interpreter())->getLogs($root);

        $this->assertCount(2, $logs);
        $this->assertEquals('08:00', $logs[0]->start->format('H:i'));
        $this->assertEquals('08:45', $logs[0]->end->format('H:i'));
        $this->assertEquals('08:45', $logs[1]->start->format('H:i'));
        $this->assertNull($logs[1]->end);
        $this->assertEquals('08:30', $logs[1]->children[0]->start->format('H:i'));
        $this->assertEquals('08:35', $logs[1]->children[0]->end->format('H:i'));

        $chargeableItems = $this->exporter->aggregateUncommitedLogs($logs);

        $this->assertCount(1, $chargeableItems);
        $this->assertEquals(50, $chargeableItems[0]['minutes']);
    }

    /** @test */
    public function should_detect_next_day()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            $in =
                <<<TEXT
01.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

23:00
TEST-10 Some content
02.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
02:00
TEXT
        );

        $logs = (new Interpreter())->getLogs($root);
        $aggregated = $this->exporter->aggregateUncommitedLogs($logs);

        $this->assertCount(1, $aggregated);
        $this->assertEquals(180, $aggregated[0]['minutes']);
    }

    /** @test */
    public function should_detect_multiple_days()
    {
        $parser = new Parser(new Config());
        $root = $parser->parse(
            $in =
                <<<TEXT
01.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

23:00
TEST-10 Some content
03.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
02:00
TEXT
        );

        $logs = (new Interpreter())->getLogs($root);
        $items = $this->exporter->aggregateUncommitedLogs($logs);

        $this->assertCount(1, $items);
        $this->assertEquals(1620, $items[0]['minutes']);
    }

    /** @test */
    public function should_remove_duplicates()
    {
        $item = new Item(null, ['Review', 'Review; Testing'], '08:00', '09:00');
        $this->assertEquals('Review; Testing', $item->getComment());
    }

    /** @test */
    public function should_remove_empty_logs()
    {
        $item = new Item(null, ['Review ', '', 'Review; Testing'], '08:00', '09:00');
        $this->assertEquals('Review; Testing', $item->getComment());
    }

    /** @test */
    public function should_remove_trailing_empty_logs()
    {
        $item = new Item(null, ['Review ', '', ''], '08:00', '09:00');
        $this->assertEquals('Review', $item->getComment());
    }

}
