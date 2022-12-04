<?php

namespace App;

use App\Model\Issue;
use App\Model\Node;
use DateInterval;
use DateTime;
use RuntimeException;
use Symfony\Component\Console\Color;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class Interactor
{

    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly QuestionHelper $questionHelper,
        private readonly ?Importer $importer = null,
    ) {

    }

    public function parseTime($time, $default = null)
    {
        if (preg_match('/^\d+$/', $time)) {
            return (new DateTime())->format('H').':'.str_pad($time, 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^\+(\d+)$/', $time, $matches)) {
            return (new DateTime($default))->add(new DateInterval('PT'.$matches[1].'M'))->format('H:i');
        }

        if (preg_match('/^-(\d+)$/', $time, $matches)) {
            return (new DateTime($default))->sub(new DateInterval('PT'.$matches[1].'M'))->format('H:i');
        }

        if (!preg_match('/^(\d+):(\d+)$/', $time, $matches)) {
            throw new RuntimeException('Invalid time');
        }

        return (new DateTime($default))->setTime($matches[1], $matches[2])->format('H:i');
    }

    public function promptTime($prompt, $default = null, $nullAllowed = true)
    {
        $white = new Color('white');
        $gray = new Color('gray');
        $cyan = new Color('cyan');

        $delete = false;
        $formats = ['h:m', 'm', '+m', '-m'];
        $prompt .= $white->apply(' (').$gray->apply('[').join(
                $gray->apply(']').'/'.$gray->apply('['),
                array_map(fn($format) => $cyan->apply($format), $formats)
            ).$gray->apply(']').')'.': ';

        $question = new Question($prompt, $default);

        $question->setValidator(function ($time) use ($nullAllowed, &$delete, $default) {
            if ($time == "d" && $nullAllowed) {
                $delete = true;

                return null;
            }

            return $this->parseTime($time, $default);
        });

        $time = $this->questionHelper->ask($this->input, $this->output, $question);

        if ($delete) {
            return null;
        }

        return $time;
    }

    public function getCommentsOfIssue(Node $root, string $issue, $limit = 20)
    {
        $issues = $root->getByCriteria(fn(Node $node) => $node instanceof Issue && $issue === $node->issue);
        $choices = array_map(fn(Issue $x) => $x->comment, $issues);

        $choices = array_reduce($choices, function ($carry, $item) {
            if ($item === '') {
                return $carry;
            }
            $items = explode('; ', $item);

            return array_merge($carry, $items);
        }, []);
        $choices = array_reverse($choices);
        $choices = array_unique($choices);

        return array_slice($choices, 0, $limit);
    }

    public function getPopularComments(Node $root, $limit = 20, $blacklist = [])
    {
        $issues = $root->getByCriteria(fn(Node $node) => $node instanceof Issue);
        $choices = array_map(fn(Issue $x) => $x->comment, $issues);

        $cntPerChoice = array_reduce($choices, function ($carry, $item) use ($blacklist) {
            if ($item === '') {
                return $carry;
            }
            $items = explode('; ', $item);

            foreach ($items as $item) {
                if (in_array($item, $blacklist)) {
                    continue;
                }
                if (!isset($carry[$item])) {
                    $carry[$item] = 0;
                }
                $carry[$item]++;
            }

            return $carry;
        }, []);

        arsort($cntPerChoice);

        return array_slice(array_keys($cntPerChoice), 0, $limit);
    }

    /**
     * @param $choices
     */
    public function combineWithKeys($choices)
    {
        $keys = array_slice(array_merge(range(1, 9), range('a', 'z')), 0, count($choices));

        return array_combine($keys, array_slice($choices, 0, count($keys)));
    }

    public function promptComment(Node $root, $issue)
    {
        $choices = [];
        if ($issue) {
            $choices = $this->getCommentsOfIssue($root, $issue);
        }
        $popular = $this->getPopularComments($root, 20 - count($choices), $choices);
        if ($popular) {
            if ($choices) {
                $choices[] = '---';
            }
            $choices = array_merge($choices, $popular);
        }

        return $this->select('Comment: ', $choices);
    }

    /**
     * @return mixed
     */
    public function getIssue(Node $root, $prompt = 'Issue'): ?string
    {
        $issues = $root->getByCriteria(function (Node $node) {
            return $node instanceof Issue && $node->alias;
        });
        $choices = array_map(function (Issue $issue) {
            return $issue->alias;
        }, $issues);
        $choices = array_reverse($choices);
        $choices = array_unique($choices);
        $choices = array_slice($choices, 0, 20);
        $choices = array_map(function ($choice) {
            return $choice.'  '.$this->importer->getSummary($choice);
        }, $choices);

        $selected = $this->select($prompt, $choices);

        return explode(' ', $selected)[0] ?? null;;
    }

    private function select($prompt, $choices)
    {
        if (!$choices) {
            $question = new Question($prompt, null);

            return $this->questionHelper->ask($this->input, $this->output, $question);
        }

        $choices = $this->combineWithKeys($choices);

        $question = new ChoiceQuestion($prompt, $choices, null);
        $question->setValidator(function ($answer) use ($choices) {
            if (array_key_exists($answer, $choices)) {
                return $choices[$answer];
            }

            return $answer;
        });

        return $this->questionHelper->ask($this->input, $this->output, $question);
    }

    public function confirm($prompt, $default = false)
    {
        $cyan = new Color('cyan');
        $question = new ConfirmationQuestion($cyan->apply($prompt . '(y/n): '), $default);

        return $this->questionHelper->ask($this->input, $this->output, $question);
    }

    public function prompt($prompt, $default = false)
    {
        $cyan = new Color('cyan');
        $gray = new Color('gray');
        $question = new Question($cyan->apply($prompt).($default ? ' '.$gray->apply('('.$default.')') : '') .': ', $default);

        if (!$question) {
            return $default;
        }

        return $this->questionHelper->ask($this->input, $this->output, $question);
    }

    public function error($message)
    {
        $color = new Color('red');
        $this->output->writeln($color->apply($message));
    }

    public function warn($message)
    {
        $color = new Color('yellow');
        $this->output->writeln($color->apply($message));
    }

    public function info($message)
    {
        $color = new Color('bright-blue');
        $this->output->writeln($color->apply($message));
    }

    public function success($message)
    {
        $color = new Color('bright-green');
        $this->output->writeln($color->apply($message));
    }

    public function writeln($message)
    {
        $this->output->writeln($message);
    }

    public function write($message)
    {
        $this->output->write($message);
    }

}
