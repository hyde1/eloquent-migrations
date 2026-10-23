<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Support;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Answers console questions from a predefined list, then with the question default value.
 *
 * Commands calling sub-commands (AbstractCommand::call()) build a new ArrayInput which does not
 * share the tester input stream: a regular QuestionHelper would block on the real STDIN.
 * As the helper set is shared by the whole application, this helper answers sub-commands too.
 */
final class ScriptedQuestionHelper extends QuestionHelper
{
    /** @var list<string> */
    private array $askedQuestions = [];

    /**
     * @param list<string> $answers
     */
    public function __construct(private array $answers)
    {
    }

    public function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
    {
        $this->askedQuestions[] = $question->getQuestion();
        $output->writeln($question->getQuestion());

        if ($this->answers === []) {
            return $question->getDefault();
        }

        $answer = array_shift($this->answers);
        $normalizer = $question->getNormalizer();

        return $normalizer === null ? $answer : $normalizer($answer);
    }

    /**
     * @return list<string>
     */
    public function getAskedQuestions(): array
    {
        return $this->askedQuestions;
    }
}
