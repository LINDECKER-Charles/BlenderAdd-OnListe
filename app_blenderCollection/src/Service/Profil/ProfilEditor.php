<?php

namespace App\Service\Profil;

use App\Entity\User;
use App\Service\AdminLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProfilEditor
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private AdminLogger $logger,
        private Security $security
    ) {}

    /**
     * Supprime un utilisateur de la base de données, le déconnecte et invalide sa session.
     *
     * À utiliser pour les suppressions initiées par l'utilisateur lui-même.
     *
     * @param User $user L'utilisateur à supprimer et à déconnecter.
     */
    public function deleteUserAndLogout(User $user): void
    {
        $this->logger->log(
        'Suppression et déconnexion utilisateur',
        $this->security->getUser(),
        $user->getName() . ' #' . $user->getId(),
        'Suppression effectuée avec déconnexion immédiate.');

        $this->em->remove($user);
        $this->em->flush();

        $this->tokenStorage->setToken(null);
        $session = $this->requestStack->getSession();
        $session->invalidate();
    }

    /**
     * Supprime définitivement un utilisateur de la base de données.
     *
     * À utiliser lorsque la suppression de compte ne nécessite pas de déconnexion immédiate.
     *
     * @param User $user L'utilisateur à supprimer.
     */
    public function deleteUser(User $user): void
    {
        $this->logger->log(
        'Suppression utilisateur',
        $this->security->getUser(),
        $user->getName() . ' #' . $user->getId(),
        'Suppression effectué');
        
        $this->em->remove($user);
        $this->em->flush();
    }
}
