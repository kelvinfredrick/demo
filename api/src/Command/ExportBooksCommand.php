<?php

namespace App\Command;

use App\Entity\Book;
use App\Repository\BookRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:export-books',
    description: 'Export books to a JSON file',
)]
class ExportBooksCommand extends Command
{
    public function __construct(
        private BookRepository $bookRepository,
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'Directory to save the exported file', 'var/export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $books = $this->bookRepository->findAll();
        $exportData = [];

        foreach ($books as $book) {
            $exportData[] = [
                'id' => $book->getId(),
                'author' => $book->author,
                'title' => $book->title,
                'categories' => $book->getCategories()->map(fn($category) => $category->getName())->toArray(),
                'reviews' => $book->reviews->count(),
                'activeUsers' => $this->getActiveUsers($book),
            ];
        }

        $directory = $input->getOption('directory');
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $filePath = $projectDir . '/' . $directory . '/books.json';

        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($filePath));
        $filesystem->dumpFile($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        $output->writeln(sprintf('Books exported to %s', $filePath));

        return Command::SUCCESS;
    }

    private function getActiveUsers(Book $book): array
    {
        $reviewUsers = $book->reviews->map(fn($review) => $review->user->email)->toArray();

        return array_values(array_intersect($reviewUsers));
    }
}