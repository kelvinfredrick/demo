<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\BookRepository\BookRepositoryInterface;
use App\Entity\Book;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @implements ProcessorInterface<Book, Book>
 */
final readonly class BookPersistProcessor implements ProcessorInterface
{
    /**
     * @param PersistProcessor $persistProcessor
     */
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private ProcessorInterface $persistProcessor,
        private BookRepositoryInterface $bookRepository,
        // private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger
    ) {
    }

    /**
     * @param Book $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Book
    {
        $book = $this->bookRepository->find($data->book);

        // this should never happen
        if (!$book instanceof Book) {
            throw new NotFoundHttpException();
        }

        if ($data instanceof Book) {
            $data->computeSlug($this->slugger);
        }

        $data->title = $book->title;
        $data->author = $book->author;

        // save entity
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}