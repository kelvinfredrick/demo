<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReviewRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:most-reviewed-day',
    description: 'Displays the day or month with the highest number of published reviews.',
)]
final class MostReviewedDayCommand extends Command
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'by-month',
                'm',
                InputOption::VALUE_NONE,
                'Display results by month instead of by day'
            );
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $byMonth = $input->getOption('by-month');

        $result = $byMonth
            ? $this->reviewRepository->findMostReviewedMonth()
            : $this->reviewRepository->findMostReviewedDay();

        if (!$result) {
            $io->error('No reviews found.');
            return Command::FAILURE;
        }

        $dateFormat = $byMonth ? 'Y-m' : 'Y-m-d';
        $periodType = $byMonth ? 'month' : 'day';
        $io->success(sprintf(
            'The %s with the most reviews (%d) was %s',
            $periodType,
            $result['reviewCount'],
            $result['publishDate']->format($dateFormat)
        ));

        return Command::SUCCESS;
    }
}
