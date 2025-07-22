<?php

namespace App\Repository;

use App\Entity\Liste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Liste>
 */
class ListeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Liste::class);
    }
    public function findVisibleOrderByDownloadDesc(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isVisible = :visible')
            ->setParameter('visible', true)
            ->orderBy('l.download', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByCreationDate(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT DATE(date_creation) as date, COUNT(id) as count
            FROM liste
            GROUP BY date
            ORDER BY date ASC
        ';
        $result = $conn->executeQuery($sql)->fetchAllAssociative();

        return $result;
    }

//    /**
//     * @return Liste[] Returns an array of Liste objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('l.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Liste
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
