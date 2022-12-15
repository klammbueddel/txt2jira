<?php

namespace App\Tests;

use DateTime;
use SlopeIt\ClockMock\ClockMock;

final class ControllerTest extends AbstractControllerTest
{

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
    public function should_start_with_last_comment(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:00'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1 baa
09:30
TEXT,
        );
        $this->controller->start('TEST-1');

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1 baa
09:30

10:00
TEST-1 baa
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_start_at_duplicate_start(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:00'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1 baa
09:30
TEXT,
        );
        $this->controller->start('TEST-2', '', '09:00');

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1 baa
09:30

09:00
TEST-2
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_start_with_last_comment_from_alias(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:00'));

        $this->controller->parse(
            <<<TEXT
TEST-1 as foo
25.11.2022

09:00
foo baa
09:30
TEXT,
        );
        $this->controller->start('foo');

        $this->assertEquals(
            <<<TEXT
TEST-1 as foo
25.11.2022

09:00
foo baa
09:30

10:00
foo baa
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_load_existing_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents(
            $tmpFile,
            <<<TEXT
25.11.2022

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
25.11.2022

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
25.11.2022

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
25.11.2022

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

        $this->controller->stop(null, -5);

        $this->assertEquals(
            <<<TEXT
25.11.2022

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
25.11.2022

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
25.11.2022

09:00
TEST-1
10:00
TEXT,
        );

        $this->controller->delete();

        $this->assertEquals(
            <<<TEXT
25.11.2022

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
25.11.2022

09:00
TEST-1
TEXT,
        );

        $this->controller->delete();

        $this->assertEquals(
            <<<TEXT
25.11.2022
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_not_delete_last_end_time(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30
TEST-2
TEXT,
        );

        $this->controller->delete();

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_delete_time_and_line_break(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEXT,
        );

        $this->controller->delete();

        $this->assertEquals(
            <<<TEXT
25.11.2022
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_delete_issue_with_mulit_line(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30

10:00
TEST-2
some more comments

TEXT,
        );

        $this->controller->delete();

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30

10:00
TEST-2

TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_edit_start_time_of_current_issue(): void
    {
        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30
TEST-1
TEXT,
        );
        $this->controller->editTime('09:45', false);

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:45
TEST-1
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_edit_start_time_of_current_issue_without_changing_end_time_of_last_one(): void
    {
        ClockMock::freeze(new DateTime('2022-11-25 10:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30
TEST-1
TEXT,
        );
        $this->controller->editTime('09:45');

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30

09:45
TEST-1
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_edit_start_time_of_current_issue_if_terminated(): void
    {
        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30
TEST-2
10:00
TEXT,
        );
        $this->controller->editTime('5m', false, true);

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:35
TEST-2
10:00
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_edit_start_time_of_current_issue_if_terminated_with_break(): void
    {
        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30
TEST-2
10:00
TEXT,
        );
        $this->controller->editTime('5m', true, true);

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30

09:35
TEST-2
10:00
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_edit_end_time_of_current_issue(): void
    {
        $this->controller->parse(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:25
TEXT,
        );
        $this->controller->editTime('5m');

        $this->assertEquals(
            <<<TEXT
25.11.2022

09:00
TEST-1
09:30
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_edit_time_of_last_day(): void
    {
        ClockMock::freeze(new DateTime('2022-11-26 00:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

23:00
TEST-1

26.11.2022

00:25
TEXT,
        );
        $this->controller->editTime('-30m');

        $this->assertEquals(
            <<<TEXT
25.11.2022

23:00
TEST-1
23:55

26.11.2022

TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_create_new_day(): void
    {
        ClockMock::freeze(new DateTime('2022-11-26 00:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

23:00
TEST-1
23:55
TEXT,
        );
        $this->controller->editTime('30m');

        $this->assertEquals(
            <<<TEXT
25.11.2022

23:00
TEST-1
26.11.2022

00:25
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_move_to_last_day(): void
    {
        ClockMock::freeze(new DateTime('2022-11-26 00:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

23:00
TEST-1
23:30

26.11.2022

00:20
TEST-1
TEXT,
        );
        $this->controller->editTime('-30m', false);
//TODO: ONE LINEBREAK IS MISSING
        $this->assertEquals(
            <<<TEXT
25.11.2022

23:00
TEST-1
23:30
23:50
TEST-1

26.11.2022

TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_move_to_next_day(): void
    {
        ClockMock::freeze(new DateTime('2022-11-26 00:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

23:00
TEST-1
23:30

26.11.2022

00:20
TEST-1
TEXT,
        );
        $this->controller->editTime('1d 30m', false);

        $this->assertEquals(
            <<<TEXT
25.11.2022

23:00
TEST-1
23:30

26.11.2022

27.11.2022

00:50
TEST-1
TEXT,
            $this->controller->render()
        );
    }

    /** @test */
    public function should_move_start(): void
    {
        ClockMock::freeze(new DateTime('2022-11-26 02:25'));

        $this->controller->parse(
            <<<TEXT
25.11.2022

TEXT,
        );
        $this->controller->start('TEST-1', 'foo', null, 240);

        $this->assertEquals(
            <<<TEXT
25.11.2022

22:25
TEST-1 foo

26.11.2022

02:25
TEXT,
            $this->controller->render()
        );
    }

}
