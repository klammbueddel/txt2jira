<?php

namespace App\Tests;

use Ahc\Cli\IO\Interactor;
use App\Config;
use App\Controller;
use App\JiraClient;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SlopeIt\ClockMock\ClockMock;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ControllerTest extends TestCase
{

    private $controller = null;
    private $input = null;
    private $output = null;
    private $questionHelper = null;
    private Config $config;
    private MockObject $client;

    protected function setUp(): void
    {
        $this->config = new Config();
        if (file_exists($this->config->getJiraCache())) {
            unlink($this->config->getJiraCache());
        }
        if (file_exists($this->config->getFile())) {
            unlink($this->config->getFile());
        }
        $this->client = $this->createMock(JiraClient::class);
        $this->controller = new Controller($this->config, $this->client);

        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->questionHelper = $this->createMock(QuestionHelper::class);
        $this->controller->setIo($this->input, $this->output, $this->questionHelper);
    }

    /** @test */
    public function should_read_empty_file(): void
    {
        $this->controller->parse('');

        $logs = $this->controller->logs();

        $this->assertCount(0, $logs);
    }

    /** @test */
    public function should_start_first_log(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 09:00'));
        $this->controller->parse('');

        $this->controller->start('TEST-1');

        $logs = $this->controller->logs();
        $this->assertCount(1, $logs);
        $this->assertEquals('TEST-1', $logs[0]->issues[0]->issue);
        $this->assertEquals('2022-11-25 09:00', $logs[0]->start->format('Y-m-d H:i'));
    }

    /** @test */
    public function should_load_existing_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents(
            $tmpFile,
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1
09:30
TEXT
        );

        $this->controller->load($tmpFile);

        $this->assertCount(1, $this->controller->logs());
    }

    /** @test */
    public function should_create_file_if_not_existing(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        unlink($tmpFile);
        $this->assertFalse(file_exists($tmpFile));

        $this->controller->load($tmpFile);

        $this->assertTrue(file_exists($tmpFile));
        $this->assertCount(0, $this->controller->logs());
    }

    /** @test */
    public function should_lazy_load_root(): void
    {
        $this->controller->getRoot();
        $this->assertCount(0, $this->controller->logs());
    }

    /** @test */
    public function should_prompt_to_start_first_issue(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 09:00'));
        $this->questionHelper->expects($this->once())->method('ask')->willReturn('TEST-1');
        $this->controller->parse('');

        $this->controller->start();

        $logs = $this->controller->logs();
        $this->assertCount(1, $logs);
        $this->assertEquals('TEST-1', $logs[0]->issues[0]->issue);
        $this->assertEquals('2022-11-25 09:00', $logs[0]->start->format('Y-m-d H:i'));
    }

    /** @test */
    public function should_ask_to_reuse_existing_issue(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:02'));

        $this->questionHelper->expects($this->once())->method('ask')->willReturn('TEST-2');

        $this->controller->parse(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1
09:30
TEXT
        );

        $this->client->expects($this->once())
            ->method('getIssues')
            ->with(['TEST-1'])
            ->willReturn([
                [
                    'key' => 'TEST-1',
                    'fields' => ['summary' => 'Test summary'],
                ],
            ]);

        $this->controller->start();

        $this->assertEquals(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1
09:30

10:00
TEST-2
TEXT,
            $this->controller->render()
        );

        $logs = $this->controller->logs();
        $this->assertCount(3, $logs);

        $idx = 0;
        $this->assertEquals('TEST-1', $logs[$idx]->issues[0]->issue);
        $this->assertEquals('2022-11-25 09:00', $logs[$idx]->start->format('Y-m-d H:i'));
        $this->assertEquals('2022-11-25 09:30', $logs[$idx]->end->format('Y-m-d H:i'));
        $this->assertFalse($logs[$idx]->transient);

        # should add break
        $idx++;
        $this->assertCount(0, $logs[$idx]->issues);
        $this->assertEquals('2022-11-25 09:30', $logs[$idx]->start->format('Y-m-d H:i'));
        $this->assertEquals('2022-11-25 10:00', $logs[$idx]->end->format('Y-m-d H:i'));
        $this->assertFalse($logs[$idx]->transient);

        # should add transient log
        $idx++;
        $this->assertEquals('TEST-2', $logs[$idx]->issues[0]->issue);
        $this->assertEquals('2022-11-25 10:00', $logs[$idx]->start->format('Y-m-d H:i'));
        $this->assertEquals('2022-11-25 10:02', $logs[$idx]->end->format('Y-m-d H:i'));
        $this->assertTrue($logs[$idx]->transient);
    }

    /** @test */
    public function should_detect_next_day(): void
    {
        ClockMock::freeze(new DateTime('2022-11-26 00:20'));

        $this->controller->parse(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

23:00
TEST-1

TEXT
        );

        $logs = $this->controller->logs();

        $this->assertCount(1, $logs);

        $idx = 0;
        $this->assertEquals('TEST-1', $logs[$idx]->issues[0]->issue);
        $this->assertEquals('2022-11-25 23:00', $logs[$idx]->start->format('Y-m-d H:i'));
        $this->assertEquals('2022-11-26 00:20', $logs[$idx]->end->format('Y-m-d H:i'));
        $this->assertTrue($logs[$idx]->transient);

        $this->controller->stop('+5');

        $this->assertEquals(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

23:00
TEST-1
00:25

TEXT,
            $this->controller->render()
        );

        $logs = $this->controller->logs();
        $this->assertCount(1, $logs);

        $idx = 0;
        $this->assertEquals('TEST-1', $logs[$idx]->issues[0]->issue);
        $this->assertEquals('2022-11-25 23:00', $logs[$idx]->start->format('Y-m-d H:i'));
        $this->assertEquals('2022-11-26 00:25', $logs[$idx]->end->format('Y-m-d H:i'));
        $this->assertFalse($logs[$idx]->transient);
    }

    /** @test */
    public function should_export_to_jira(): void
    {
        $this->client->expects($this->once())
            ->method('getIssues')
            ->with(['TEST-1'])
            ->willReturn([
                [
                    'key' => 'TEST-1',
                    'fields' => ['summary' => 'Test summary'],
                ],
            ]);

        $this->questionHelper->expects($this->once())->method('ask')->willReturn('TEST-1');
        $this->controller->parse(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1
10:00
TEXT,
        );
        $logs = $this->controller->logs();
        $this->assertFalse($logs[0]->issues[0]->isDone);
        $this->controller->commitToJira();
        $this->assertTrue($logs[0]->issues[0]->isDone);
    }

    /** @test */
    public function should_delete_break(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1
10:00
TEXT,
        );

        $this->controller->delete();

        $this->assertEquals(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_delete_issue(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

09:00
TEST-1
TEXT,
        );

        $this->controller->delete();

        $this->assertEquals(
            <<<TEXT
25.11.2022 +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
TEXT,
            $this->controller->render()
        );
    }

}
