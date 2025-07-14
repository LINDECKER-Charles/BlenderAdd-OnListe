<?php

namespace App\Service;

use App\Entity\Log;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AdminLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * Crée un log d’action administrative ou système.
     *
     * @param string      $action  Intitulé de l'action (ex : "Suppression utilisateur")
     * @param User|null   $user    Utilisateur à l’origine de l'action
     * @param string|null $target  Élément ciblé par l'action (ex : "User #15", "Liste #32", etc.)
     * @param string|null $details Détails facultatifs (JSON, texte brut, etc.)
     */
    public function log(string $action, ?User $user = null, ?string $target = null, ?string $details = null): void
    {
        $log = new Log();
        $log->setAction($action)
            ->setUser($user)
            ->setTarget($target)
            ->setDetails($details);

        $this->em->persist($log);
        $this->em->flush();
    }
}
