<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 *
 * @method Review|null find($id, $lockMode = null, $lockVersion = null)
 * @method Review|null findOneBy(array $criteria, array $orderBy = null)
 * @method Review[]    findAll()
 * @method Review[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function findMostReviewedDay(): ?array
    {
        return $this->createQueryBuilder('r')
            ->select('DATE(r.publishDate) as publishDate, COUNT(r.id) as reviewCount')
            ->groupBy('publishDate')
            ->orderBy('reviewCount', 'DESC')
            ->addOrderBy('publishDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findMostReviewedMonth(): ?array
    {
        return $this->createQueryBuilder('r')
            ->select('DATE_FORMAT(r.publishDate, \'%Y-%m-01\') as publishDate, COUNT(r.id) as reviewCount')
            ->groupBy('publishDate')
            ->orderBy('reviewCount', 'DESC')
            ->addOrderBy('publishDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAverageRating(Book $book): ?int
    {
        $rating = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->where('r.book = :book')->setParameter('book', $book)
            ->getQuery()->getSingleScalarResult()
        ;

        return $rating ? (int) $rating : null;
    }

    public function save(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}