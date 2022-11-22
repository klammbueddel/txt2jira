<?php

namespace App\Tests;

use Ahc\Cli\IO\Interactor;
use App\Config;
use App\Exporter;
use App\Importer;
use App\Item;
use App\JiraClient;
use PHPUnit\Framework\TestCase;

final class ExporterTest extends TestCase
{

    /** @test */
    public function should_perform_real_export()
    {
        $this->markTestSkipped('do not skip to test real export');

        $importer = new Importer();
        $days = $importer->import('log.txt');
        $exporter = new Exporter(new JiraClient(new Config()), new Interactor());
        $logs = $exporter->prepare($days);
        $exporter->export($logs);
    }

    /** @test */
    public function should_aggregate_times()
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

        $exporter = new Exporter(null, null);
        $logs = $exporter->prepare($days);

        $this->assertEquals([
            [
                'date' => new \DateTime('11.10.2022 08:00'),
                'issue' => 'TEST-10',
                'alias' => 'TEST-10',
                'minutes' => 30,
                'total' => 30,
                'comment' => 'Review',
                'allComments' => 'Review',
                'items' => [$days['11.10.2022'][0], $days['11.10.2022'][1]],
            ],
        ], $logs);

        $out = $importer->render($days);
        $this->assertEquals(str_replace("\r\n", "\n", $in), $out);
    }

    /** @test */
    public function should_filter_todo()
    {
        $importer = new Importer();
        $days = $importer->parse(
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

        $exporter = new Exporter(null, null);
        $logs = $exporter->prepare($days);

        $this->assertEquals([
            [
                'date' => new \DateTime('11.10.2022 08:15'),
                'issue' => 'TEST-10',
                'alias' => 'TEST-10',
                'minutes' => 15,
                'total' => 30,
                'comment' => 'Some more content',
                'allComments' => 'Already charged, Some more content',
                'items' => [$days['11.10.2022'][1]],
            ],
        ], $logs);

        $this->assertFalse($days['11.10.2022'][1]->isDone());
        $out = $importer->render($days);
        $this->assertEquals(str_replace("\r\n", "\n", $in), $out);
    }

    /** @test */
    public function should_ignore_invalid_date()
    {
        $importer = new Importer();
        $days = $importer->parse(
            $in =
                <<<TEXT
00.10.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

08:00
TEST-10 Some content
08:30


TEXT
        );

        $exporter = new Exporter(null, null);
        $logs = $exporter->prepare($days);

        $this->assertCount(0, $logs);
    }

    /** @test */
    public function should_remove_duplicates()
    {
        $item = new Item(null, ['Review', 'Review, Testing'], '08:00', '09:00');
        $this->assertEquals('Review, Testing', $item->getComment());
    }

    /** @test */
    public function should_remove_empty_logs()
    {
        $item = new Item(null, ['Review ', '', 'Review, Testing'], '08:00', '09:00');
        $this->assertEquals('Review, Testing', $item->getComment());
    }

    /** @test */
    public function should_remove_trailing_empty_logs()
    {
        $item = new Item(null, ['Review ', '', ''], '08:00', '09:00');
        $this->assertEquals('Review', $item->getComment());
    }

    /** @test */
    public function should_format_jira_time()
    {
        $this->assertEquals('1h', Exporter::formatJiraTime(3600, 0));
        $this->assertEquals('1h 30m', Exporter::formatJiraTime(3600 + 1800, 0));
        $this->assertEquals('1h 30m 1s', Exporter::formatJiraTime(3600 + 1800 + 1, 0));
    }

}
