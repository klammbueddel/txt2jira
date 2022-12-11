<?php

namespace App\Tests;

use App\Commands\LogCommand;
use DateTime;
use SlopeIt\ClockMock\ClockMock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class LogCommandTest extends AbstractControllerTest
{

    private $command;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application();
        $app->add($this->command = new LogCommand());
    }

    /** @test */
    public function should_start_first_log(): void
    {
        $app = new Application();
        $app->add($this->command);
        ClockMock::freeze(new DateTime('2022-11-25 09:00'));
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'issue' => 'TEST-1',
            '--file' => 'tests/.txt2jira',
        ]);

        $logs = $this->controller->logs();
        $this->assertCount(1, $logs);
        $this->assertEquals('TEST-1', $logs[0]->issues[0]->issue);
        $this->assertEquals('2022-11-25 09:00', $logs[0]->start->format('Y-m-d H:i'));
    }

    /** @test */
    public function should_change_current_comment(): void
    {
        $app = new Application();
        $app->add($this->command);
        ClockMock::freeze(new DateTime('2022-11-25 09:00'));
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'issue' => 'TEST-1',
            '--file' => 'tests/.txt2jira',
        ]);

        $commandTester->execute([
            'issue' => 'TEST-1 Add some comment',
            '--file' => 'tests/.txt2jira',
        ]);

        $logs = $this->controller->logs();
        $this->assertCount(1, $logs);
        $this->assertEquals('TEST-1', $logs[0]->issues[0]->issue);
        $this->assertEquals('Add some comment', $logs[0]->issues[0]->comment);
        $this->assertEquals('2022-11-25 09:00', $logs[0]->start->format('Y-m-d H:i'));
    }

    /** @test */
    public function should_stop_after_duration(): void
    {
        $app = new Application();
        $app->add($this->command);
        ClockMock::freeze(new DateTime('2022-11-25 09:30'));
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'issue' => '09:00 5m TEST-1 foo',
            '--file' => 'tests/.txt2jira',
        ]);

        $this->assertEquals(
            <<<TEXT

25.11.2022

09:00
TEST-1 foo
09:05

TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_start_before_duration(): void
    {
        $app = new Application();
        $app->add($this->command);
        ClockMock::freeze(new DateTime('2022-11-25 09:30'));
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'comments' => explode(' ', '10m TEST-1 foo'),
            '--file' => 'tests/.txt2jira',
        ]);

        $this->assertEquals(
            <<<TEXT

25.11.2022

09:20
TEST-1 foo
09:30

TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_use_time(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 09:30'));
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'time' => '09:00',
            'issue' => 'TEST-1',
            'comments' => explode(' ', 'foo'),
            '--file' => 'tests/.txt2jira',
        ]);

        $this->assertEquals(
            <<<TEXT

25.11.2022

09:00
TEST-1 foo

TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_stop_at_time()
    {
        ClockMock::freeze(new DateTime('2022-11-25 15:00'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

09:30
TEST-1
TEXT,
        );
        $this->controller->save();

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'issue' => '11:00',
            '--file' => 'tests/.txt2jira',
        ]);

        $this->controller->load();

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:30
TEST-1
11:00
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_stop_before_duration()
    {
        ClockMock::freeze(new DateTime('2022-11-25 15:02'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

09:30
TEST-1
TEXT,
        );
        $this->controller->save();

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'issue' => '1h 30m',
            '--file' => 'tests/.txt2jira',
        ]);

        $this->controller->load();

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:30
TEST-1
13:30
TEXT,
            $this->controller->render()
        );
    }

}
