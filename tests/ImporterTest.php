<?php

namespace App\Tests;

use App\Config;
use App\HttpException;
use App\Importer;
use App\JiraClient;
use Exception;
use PHPUnit\Framework\TestCase;

final class ImporterTest extends TestCase
{
    private $client;
    private $config;

    protected function setUp(): void
    {
        $this->client = $this->createMock(JiraClient::class);
        $this->config = new Config();
        if (file_exists($this->config->getJiraCache())) {
            unlink($this->config->getJiraCache());
        }
    }

    public function createImporter()
    {
        return new Importer($this->client, $this->config);
    }

    /** @test */
    public function should_resolve_summary()
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

        $this->assertEquals('Test summary', $this->createImporter()->getSummary('TEST-1'));

        # second call should be cached
        $this->assertEquals('Test summary', $this->createImporter()->getSummary('TEST-1'));
    }

    /** @test */
    public function should_cache_invalid_issues()
    {
        $this->client->expects($this->once())
            ->method('getIssues')
            ->with(['TEST-1'])
            ->willThrowException(new HttpException('{"errorMessages":["The issue key \'TEST-1\' for field \'key\' is invalid."],"warningMessages":[]}'));

        $this->assertEquals('ERROR: The issue key is invalid.', $this->createImporter()->getSummary('test-1'));

        # second call should be cached
        $this->assertEquals('ERROR: The issue key is invalid.', $this->createImporter()->getSummary('test-1'));
    }

    /** @test */
    public function should_show_error()
    {
        $this->client->expects($this->once())
            ->method('getIssues')
            ->with(['TEST-1'])
            ->willThrowException(new HttpException('{"errorMessages":["Issue does not exist or you do not have permission to see it."],"warningMessages":[]}', 400));

        $this->assertEquals('ERROR: Issue does not exist or you do not have permission to see it.', $this->createImporter()->getSummary('TEST-1'));

        # second call should be cached
        $this->assertEquals('ERROR: Issue does not exist or you do not have permission to see it.', $this->createImporter()->getSummary('TEST-1'));
    }

}
