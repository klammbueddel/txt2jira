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
    private Importer $importer;
    private Renderer $renderer;
    private Interpreter $interpreter;
    private Interactor $io;
    private ?Node $_root = null;

    /**
     * @return Importer
     */
    public function getImporter(): Importer
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
        $this->io = new Interactor($input, $output, $helper, $this->getImporter());
    }

    public function __construct(
        private readonly Config $config = new Config(),
        private ?JiraClient $client = null,
    ) {
        $this->client = $client ?: new JiraClient($this->config);
        $this->exporter = new Exporter($this->client);
        $this->importer = new Importer($this->client, $this->config);
        $this->renderer = new Renderer($this->importer);
        $this->interpreter = new Interpreter();
    }

    /**
     * @param string $content
     * @throws SyntaxError
     */
    public function parse(string $content): Node
    {
        $parser = new Parser($this->config);
        $this->_root = $parser->parse($content);
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

    public function stop($time = null)
    {
        $transientLogs = array_values(
            array_filter($this->logs(), function (Log $log) {
                return $log->transient;
            })
        );

        if (!$transientLogs) {
            $this->io->warn('No active log');

            return;
        }

        $lastIssue = $this->getRoot()->getIssue(true, true);
        if (!$lastIssue) {
            $this->io->warn('No active log');

            return;
        }

        $time = $time ? $this->io->parseTime($time) : $this->roundTime(new DateTime())->format('H:i');

        $lastIssue->insertSibling(new Time($time));
        $this->save();

        $this->io->info("Stopped {$lastIssue->input} at ".$time);
    }

    public function delete()
    {
        $lastIssue = $this->getRoot()->getIssue(true, true);

        if (!$lastIssue) {
            $this->io->warn('Nothing to delete');

            return;
        }

        $toDelete = [$lastIssue];
        $prev = $lastIssue->getSibling(-1);
        $start = $end = '--:--';

        if ($prev instanceof Time) {
            $start = $prev->time;
            if ($prev->getSibling(-1) instanceof EmptyLine) {
                $toDelete[] = $prev;
                $toDelete[] = $prev->getSibling(-1);
            }
        }

        $next = $lastIssue->getSibling();
        if ($next instanceof Time) {
            $toDelete[] = $next;
            $end = $next->time;
        }

        foreach ($toDelete as $node) {
            $node->delete();
        }

        $this->io->info('Deleted '.$start.' - '.$end.'  '.$lastIssue->input);
        $this->save();
    }

    public function editTime($time = null)
    {
        $lastTime = $this->getRoot()->getOneByType(Time::class, true, true);

        if (!$lastTime) {
            $this->io->warn('No time found');

            return;
        }

        $time = $time ? $this->io->parseTime($time) : $this->io->promptTime('Edit', $lastTime->time);
        if ($time) {
            $lastTime->time = $time;
            $this->io->info("Set time to $time");
        } else {
            $lastTime->delete();
            $this->io->info("Deleted ".$lastTime->time);
        }
        $this->save();
    }

    public function start($issue = null, $comment = '', $time = null)
    {
        $time = $time ? $this->io->parseTime($time) : $this->roundTime(new DateTime())->format('H:i');
        $issue = $issue ?? $this->io->getIssue($this->getRoot(), "Start at ".$time .': ');

        $today = $this->today();

        $now = $today->getOneByCriteria(function (Node $node) use ($time) {
            return $node instanceof Time && $node->time === $time;
        }, false, true);

        $lastIssue = $this->getRoot()->getOneByCriteria(function (Node $node) use ($issue) {
            return $node instanceof Issue && (!$issue || $node->issue === $issue);
        }, true, true);

        if ($comment) {
            $input = $lastIssue ? $lastIssue->alias.' '.$comment : $issue.' '.$comment;
        } else {
            $input = $lastIssue ? $lastIssue->input : $issue;
        }

        if (!$now) {
            $now = new Time($time);
            $last = $today->getOneByCriteria(function (Node $node) {
                return !($node instanceof EmptyLine);
            }, false, true);

            if ($last) {
                $last->insertSibling($now);
                $last->insertSibling(new EmptyLine());
            } else {
                $today->prependChild($now);
                $today->prependChild(new EmptyLine());
            }
        }

        $now->insertSibling(new Issue($input, false));

        $this->save();
        $this->io->info("Started {$input} at ".$time);
    }

    public function changeIssue()
    {
        $issue = $this->io->getIssue($this->getRoot());

        if (!$issue) {
            return;
        }

        $lastNode = $this->getRoot()->getOneByCriteria(function (Node $node) {
            return $node instanceof Issue;
        }, true, true);

        if (!$lastNode) {
            $this->io->warn('No issue found');

            return;
        }

        $from = $lastNode->alias;
        $lastNode->input = $issue.' '.$lastNode->comment;

        $this->save();
        $this->io->info("Changed issue from {$from} to {$issue}");
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
            $comment = (trim($lastIssue->comment) ? '; ' : ' ').$comment;
        }

        $lastIssue->input = $lastIssue->alias.' '.$comment;
        $this->save();
        $this->io->info("Changed comment to {$comment}");
    }

    public function clearCache() {
        if (file_exists($this->config->getJiraCache())) {
            unlink($this->config->getJiraCache());
            $this->io->info('Cache cleared');
        } else {
            $this->io->info('Cache is clean');
        }
    }

    public function today(): ?Day
    {
        $today = $this->getRoot()->getOneByCriteria(function (Node $node) {
            return $node instanceof Day && $node->date === ($this->roundTime(new DateTime()))->format('d.m.Y');
        });

        if (!$today) {
            $today = new Day(($this->roundTime(new DateTime()))->format('d.m.Y'));
            $this->getRoot()->addChild($today);
            $today->addChild(new EmptyLine());
        }

        return $today;
    }

    public function roundTime(DateTime $time)
    {
        $seconds = $this->config->roundMinutes * 60;
        $time->setTime($time->format('H'), $time->format('i'), 0);
        $time->setTimestamp(round($time->getTimestamp() / $seconds) * $seconds);

        return $time;
    }

}
