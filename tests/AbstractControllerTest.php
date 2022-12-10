<?php

namespace App\Tests;

use App\Config;
use App\Controller;
use App\JiraClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractControllerTest extends TestCase
{

    protected $controller = null;
    protected $input = null;
    protected $output = null;
    protected $questionHelper = null;
    protected Config $config;
    protected MockObject $client;

    protected function setUp(): void
    {
        chdir(__DIR__.'/..');
        $this->config = new Config();
        $this->config->load(__DIR__.'/.txt2jira');
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

}
