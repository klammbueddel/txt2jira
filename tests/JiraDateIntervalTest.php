<?php

namespace App\Tests;

use App\Exporter;
use App\JiraDateInterval;
use PHPUnit\Framework\TestCase;

final class JiraDateIntervalTest extends TestCase
{

    /** @test */
    public function should_format_jira_time()
    {
        $this->assertEquals('1h', new JiraDateInterval('PT1H'));
        $this->assertEquals('1h 30m', new JiraDateInterval('PT90M'));
        $this->assertEquals('1d 1h', new JiraDateInterval('PT25H'));
        $this->assertEquals('1m', new JiraDateInterval('PT89S'));
        $this->assertEquals('2m', new JiraDateInterval('PT90S'));
    }

    /** @test */
    public function should_format_minutes()
    {
        $this->assertEquals('1h', JiraDateInterval::formatMinutes(60, 0));
        $this->assertEquals('1h 30m', JiraDateInterval::formatMinutes(90, 0));
        $this->assertEquals('-1h 30m', JiraDateInterval::formatMinutes(-90, 0));
    }

    /** @test */
    public function should_parse_time()
    {
        $this->assertEquals(60, JiraDateInterval::parse('1h')->getMinutes());
        $this->assertEquals(70, JiraDateInterval::parse('1h 10m')->getMinutes());
        $this->assertEquals(30, JiraDateInterval::parse('0.5h')->getMinutes());
        $this->assertEquals(20, JiraDateInterval::parse('0.33h')->getMinutes());
    }

}
