<?php

namespace App\Repository;

use App\Entity\Log;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Log>
 *
 * @method Log|null find($id, $lockMode = null, $lockVersion = null)
 * @method Log|null findOneBy(array $criteria, array $orderBy = null)
 * @method Log[]    findAll()
 * @method Log[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    /**
     * Récupère les derniers logs par ordre décroissant de date.
     *
     * @param int $limit Nombre maximum de logs à récupérer
     * @return Log[]
     */
    public function findLatest(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les logs associés à un utilisateur donné.
     *
     * @param int $userId
     * @return Log[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :id')
            ->setParameter('id', $userId)
            ->orderBy('l.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
