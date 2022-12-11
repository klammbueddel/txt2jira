<?php

namespace App;

use App\Model\Day;
use App\Model\EmptyLine;
use App\Model\Issue;
use App\Model\Log;
use App\Model\Node;
use App\Model\Time;
use DateTime;
use RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Controller
{
    /**
     * Will be lazy initialized, use getRoot() to fetch.
     */
    private Exporter $exporter;
    private ?Importer $importer = null;
    private Renderer $renderer;
    private Interpreter $interpreter;
    private Interactor $io;
    private Parser $parser;
    private ?Node $_root = null;

    /**
     * @return Importer|null
     */
    public function getImporter(): ?Importer
    {
        return $this->importer;
    }

    /**
     * @return Renderer
     */
    public function getRenderer(): Renderer
    {
        return $this->renderer;
    }

    public function setIo(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $this->io = new Interactor($input, $output, $helper, $this->config, $this->getImporter());
    }

    public function __construct(
        private readonly Config $config = new Config(),
        private ?JiraClient $client = null,
    ) {
        $this->client = $client ?: ($this->config->host ? new JiraClient($this->config) : null);
        $this->exporter = new Exporter($this->client);
        if ($this->client) {
            $this->importer = new Importer($this->client, $this->config);
        }
        $this->renderer = new Renderer($this->importer);
        $this->interpreter = new Interpreter();
        $this->parser = new Parser($this->config);
    }

    /**
     * @param string $content
     * @throws SyntaxError
     */
    public function parse(string $content): Node
    {
        $this->_root = $this->parser->parse($content);
        $this->interpreter->getLogs($this->_root);
        return $this->_root;
    }

    private function path($path = null)
    {
        return $path ?: $this->config->getFile();
    }

    /**
     * @param $path
     * @throws SyntaxError
     */
    public function load($path = null): Node
    {
        $path = $this->path($path);

        if (!file_exists($path)) {
            if (!touch($path)) {
                throw new RuntimeException("Could not create $path");
            }
        }

        return $this->parse(file_get_contents($path));
    }

    public function render(): string
    {
        return substr($this->getRoot()->__toString(), 0, -1);
    }

    public function save($path = null): self
    {
        $path = $this->path($path);
        if (!file_put_contents($path, $this->render())) {
            throw new RuntimeException("Could not write to $path");
        }

        return $this;
    }

    public function chargeableItems($all = false)
    {
        return $this->exporter->chargeableItems($this->logs(), $all);
    }

    public function aggregateUncommittedLogs()
    {
        return $this->exporter->aggregateUncommitedLogs($this->logs());
    }

    public function aggregateAllLogs()
    {
        return $this->exporter->aggregateAllLogs($this->logs());
    }

    public function getRoot()
    {
        if (!$this->_root) {
            $this->_root = $this->load();
        }

        return $this->_root;
    }

    /**
     * @return Log[]
     * @throws SyntaxError
     */
    public function logs(): array
    {
        return $this->interpreter->getLogs($this->getRoot());
    }

    public function commitToJira()
    {
        $chargableItems = $this->aggregateUncommittedLogs();
        $this->io->writeln($this->renderer->render($chargableItems));

        if ($this->io->confirm("Commit to Jira? ")) {
            $this->exporter->export($this->io, $chargableItems, function () {
                $this->save();
            });
            $this->io->success('All done!');
        }
    }

    public function stop($time = null, $duration = null): ?Issue
    {
        $transientLogs = array_values(
            array_filter($this->logs(), function (Log $log) {
                return $log->transient;
            })
        );

        if (!$transientLogs) {
            $this->io->warn('No active log');

            return null;
        }

        $lastIssue = $this->getRoot()->getIssue(true, true);
        if (!$lastIssue) {
            $this->io->warn('No active log');

            return null;
        }

        $time = $time
            ? $this->io->parseTime($time, "$duration minutes ago")
            : $this->roundTime(new DateTime("$duration minutes ago"));

        $time = $time->format('H:i');

        $lastIssue->parent->insertSibling(new Time($time));
        $this->save();

        $this->io->info("Stopped {$lastIssue->input} at ".$time);

        return $lastIssue;
    }

    public function delete()
    {
        $lastNode = $this->getRoot()->getOneByCriteria(function (Node $node) {
            if ($node instanceof Day) {
                # do not delete non empty day
                if ($node->getByCriteria(function (Node $node) {
                    return !$node instanceof EmptyLine;
                })) {
                    return false;
                }
            }

            return !$node instanceof EmptyLine;
        }, true, true);

        if (!$lastNode) {
            $this->io->warn('Nothing to delete');

            return;
        }

        if (($emptyLine = $lastNode->getSibling(-1)) instanceof EmptyLine) {
            $emptyLine->delete();
        }
        $lastNode->delete();

        $this->io->info('Deleted '. trim($lastNode));
        $this->save();
    }

    public function editStartTime($time = null, $insertBreak = true)
    {
        $lastIssue = $this->getRoot()->getIssue(true, true);

        if (!$lastIssue->parent instanceof Time) {
            $this->io->warn('No start time found');

            return;
        }

        return $this->editTimeNode($time, $lastIssue->parent, $insertBreak);
    }

    public function editTime($time = null, $insertBreak = true, $editStartTime = null)
    {
        $lastTime = $this->getRoot()->getOneByType(Time::class, true, true);

        if (!$lastTime) {
            $this->io->warn('No time found');

            return;
        }

        if ($editStartTime) {
            return $this->editStartTime($time, $insertBreak);
        }

        if (! $lastTime->children) {
            $insertBreak = false;
        }

        $this->editTimeNode($time, $lastTime, $insertBreak);
    }

    private function appendNode(Node $parent, Node $node, $addEmptyLine = false)
    {
        $lastNode = $parent->getOneByCriteria(function (Node $node) {
            return !$node instanceof EmptyLine;
        }, true, true);

        if ($lastNode) {
            $lastNode->insertSibling($node);
            if ($addEmptyLine) {
                $lastNode->insertSibling(new EmptyLine());
            }
        } else {
            $parent->addChild($node);
            if ($addEmptyLine) {
                $parent->addChild(new EmptyLine());
            }
        }
    }

    public function start($issue = null, $comment = '', $time = null, $duration = 0)
    {
        $dateTime =
            $time ? $this->io->parseTime($time, "$duration minutes ago") :
                $this->roundTime(new DateTime("$duration minutes ago"));
        $time = $dateTime->format('H:i');
        $issue = $issue ?: $this->io->getIssue($this->getRoot(), "Start at ".$time.': ');

        $now = $this->now($dateTime);

        $lastIssue = $this->getRoot()->getOneByCriteria(function (Node $node) use ($issue) {
            return $node instanceof Issue && (!$issue || $node->issue === $issue || $node->alias === $issue);
        }, true, true);

        if ($comment) {
            $input = $lastIssue ? $lastIssue->alias.' '.$comment : $issue.' '.$comment;
        } else {
            $input = $lastIssue ? $lastIssue->input : $issue;
        }

        $now->addChild(new Issue($input, false));

        $this->save();
        $this->io->info("Started {$input} at ".$time);

        if ($duration) {
            $endDateTime = $dateTime->add(new \DateInterval('PT'.$duration.'M'));
            $endTime = $endDateTime->format('H:i');
            $this->appendNode($this->today($endDateTime), new Time($endTime));
            $this->save();
            $this->io->info("Stopped {$input} at ".$endTime);
        }
    }

    public function changeIssue($issue = null, $comment = null)
    {
        $issue = $issue ?: $this->io->getIssue($this->getRoot());

        if (!$issue) {
            return;
        }

        $lastIsue = $this->getRoot()->getIssue(true, true);

        if (!$lastIsue) {
            $this->io->warn('No issue found');

            return;
        }

        $from = $lastIsue->alias.' '.$lastIsue->comment;
        $lastIsue->input = $issue.' '.($comment ?: $lastIsue->comment);
        $to = $lastIsue->input;

        if ($from === $to) {
            $this->io->info("Issue is already '$from'");

            return;
        }

        $this->save();
        $this->io->info("Changed issue from '{$from}' to '{$to}'");
    }

    public function comment($append, $comment = null)
    {
        $lastIssue = $this->getRoot()->getIssue(true, true);
        if (!$lastIssue) {
            $this->io->warn('No issue found');

            return;
        }

        $comment = $comment ?: $this->io->promptComment($this->getRoot(), $lastIssue->issue);
        if (!$comment) {
            return;
        }

        if ($append) {
            $comment = (trim($lastIssue->comment) ? trim($lastIssue->comment).'; ' : '').$comment;
        }

        $from = $lastIssue->comment;
        $lastIssue->input = $lastIssue->alias.' '.$comment;
        $to = $comment;

        $this->save();
        $this->io->info("Changed comment from '{$from}' to '{$to}'");
    }

    public function clearCache()
    {
        if (file_exists($this->config->getJiraCache())) {
            unlink($this->config->getJiraCache());
            $this->io->info('Cache cleared');
        } else {
            $this->io->info('Cache is clean');
        }
    }

    public function today(DateTime $dateTime = null): Day
    {
        $today = $this->getRoot()->getOneByCriteria(function (Node $node) use ($dateTime) {
            return $node instanceof Day && $node->date === ($this->roundTime($dateTime ?: new DateTime()))->format(
                    $this->config->dateFormat
                );
        });

        if (!$today) {
            $today = new Day(($this->roundTime($dateTime ?: new DateTime()))->format($this->config->dateFormat));
            $this->getRoot()->addChild($today);
            $today->addChild(new EmptyLine());
        }

        return $today;
    }

    public function now(DateTime $dateTime): Time
    {
        $today = $this->today($dateTime);

        $lastTime = $today->getOneByCriteria(function (Node $node) {
            return $node instanceof Time;
        }, true, true);

        $time = $dateTime->format('H:i');
        if ($lastTime && $lastTime->time === $time) {
            return $lastTime;
        }

        $result = new Time($time);
        $this->appendNode($today, $result, true);

        return $result;
    }

    public function lastTime(): ?string
    {
        $time = $this->getRoot()->getOneByType(Time::class, true, true);

        if (!$time) {
            return null;
        }

        return $time->time;
    }

    public function tryParseTimeNode($input): ?Time
    {
        try {
            return $this->parser->parseTime($input);
        } catch (SyntaxError) {
            return null;
        }
    }

    public function roundTime(DateTime $time)
    {
        return $this->io->roundTime($time);
    }

    /**
     * @param mixed $time
     * @param Node $node
     * @param mixed $insertBreak
     * @return void
     */
    private function editTimeNode(mixed $time, Node $node, mixed $insertBreak): void
    {
        $dateTime = $time
            ? $this->io->parseTime($time, $node->parent->date. ' '. $node->time)
            : $this->io->promptTime('Edit '.$node->time, $node->parent->date. ' '. $node->time);

        $day = $this->today($dateTime);

        $time = $dateTime->format('H:i');
        if ($insertBreak) {
            $node->insertSibling($newTime = new Time($time));
            $node->insertSibling(new EmptyLine());

            foreach ($node->children as $child) {
                $newTime->addChild($child);
            }
            $node->children = [];
        } else {
            $node->time = $time;
            if ($node->parent->date !== $day->date) {
                $node->delete();
                $this->appendNode($day, $node, false);
            }
        }
        $this->io->info("Set time to $time");

        $this->save();
    }

}
