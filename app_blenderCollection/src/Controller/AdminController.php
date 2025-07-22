<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AdminLogger;
use App\Service\RoleManager;
use App\Repository\LogRepository;
use App\Service\UserAccesChecker;
use App\Repository\UserRepository;
use App\Repository\ListeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserAccesChecker $uac,
        private readonly RoleManager $roleManager,
        private readonly EntityManagerInterface $em,
        private AdminLogger $logger,
        private UserRepository $userRepository,
        private ListeRepository $listeRepository
    ) {}

    /**
     * Attribue le rôle "MODO" à un utilisateur donné.
     *
     * Vérifie que l’utilisateur connecté est administrateur.
     * Vérifie la validité du jeton CSRF.
     * Ajoute le rôle "MODO" puis flush en base.
     * Affiche un message flash de succès ou d’erreur.
     *
     * @param User $user L’utilisateur à modifier
     * @param Request $request Requête HTTP contenant le token CSRF
     * @return RedirectResponse Redirection vers la page profil visiteur de l’utilisateur
     */
    #[Route('/give-modo/{id}', name: 'admin_give_modo', methods: ['POST'])]
    public function giveModo(User $user, Request $request): RedirectResponse
    {
        // Sécurisation
        if (!$this->uac->isAdmin()) {
            
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('app_home');
        }

        if (!$this->isCsrfTokenValid('give_modo_' . $user->getId(), $request->request->get('_token'))) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_home');
        }

        $this->roleManager->giveModo($user);

        $this->logger->log(
        'Attribution rôle MODO',
        $this->getUser(),
        $user->getName() . ' #' . $user->getId(),
        'Rôle MODO attribué par ' . $this->getUser()->getName()
        );

        $request->getSession()->getFlashBag()->clear();
        $this->addFlash('success', 'Le rôle MODO a été attribué à ' . $user->getName() . '.');
        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }

    /**
     * Banni un utilisateur donné.
     *
     * Vérifie que l’utilisateur connecté est administrateur.
     * Vérifie la validité du jeton CSRF.
     * Remplace les rôles par "ROLE_BAN" puis flush en base.
     * Affiche un message flash de succès ou d’erreur.
     *
     * @param User $user L’utilisateur à bannir
     * @param Request $request Requête HTTP contenant le token CSRF
     * @return RedirectResponse Redirection vers la page profil visiteur de l’utilisateur
     */
    #[Route('/set-ban/{id}', name: 'admin_set_ban', methods: ['POST'])]
    public function setBan(User $user, Request $request): RedirectResponse
    {

        if (!$this->uac->isAdmin()) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('app_home');
        }

        if (!$this->isCsrfTokenValid('set_ban_' . $user->getId(), $request->request->get('_token'))) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_home');
        }

        $this->roleManager->setBan($user);
        
        $this->logger->log(
            'Bannissement utilisateur',
            $this->getUser(),
            $user->getName() . ' #' . $user->getId(),
            'Utilisateur banni par ' . $this->getUser()->getName()
        );

        $request->getSession()->getFlashBag()->clear();
        $this->addFlash('success', 'L\'utilisateur ' . $user->getName() . ' a été banni.');
        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }

    /**
     * Verrouille un utilisateur donné.
     *
     * Vérifie que l’utilisateur connecté est administrateur.
     * Vérifie la validité du jeton CSRF.
     * Remplace les rôles par "ROLE_LOCK" puis flush en base.
     * Affiche un message flash de succès ou d’erreur.
     *
     * @param User $user L’utilisateur à verrouiller
     * @param Request $request Requête HTTP contenant le token CSRF
     * @return RedirectResponse Redirection vers la page profil visiteur de l’utilisateur
     */
    #[Route('/set-lock/{id}', name: 'admin_set_lock', methods: ['POST'])]
    public function setLock(User $user, Request $request): RedirectResponse
    {
        if (!$this->uac->isAdmin()) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('app_home');
        }

        if (!$this->isCsrfTokenValid('set_lock_' . $user->getId(), $request->request->get('_token'))) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_home');
        }

        $this->roleManager->setLock($user);

        $this->logger->log(
        'Verrouillage utilisateur',
        $this->getUser(),
        $user->getName() . ' #' . $user->getId(),
        'Utilisateur verrouillé par ' . $this->getUser()->getName()
        );

        $request->getSession()->getFlashBag()->clear();
        $this->addFlash('success', 'L\'utilisateur ' . $user->getName() . ' a été verrouillé.');
        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }

    /**
     * Réinitialise tous les rôles d’un utilisateur donné (supprime tous les rôles).
     *
     * Vérifie que l’utilisateur connecté est administrateur.
     * Vérifie la validité du jeton CSRF.
     * Appelle la méthode resetRoles du RoleManager puis flush en base.
     * Affiche un message flash de succès ou d’erreur.
     *
     * @param User $user L’utilisateur dont les rôles sont réinitialisés
     * @param Request $request Requête HTTP contenant le token CSRF
     * @return RedirectResponse Redirection vers la page profil visiteur de l’utilisateur
     */
    #[Route('/reset-roles/{id}', name: 'admin_reset_roles', methods: ['POST'])]
    public function resetRoles(User $user, Request $request): RedirectResponse
    {
        if (!$this->uac->isAdmin()) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('app_home');
        }

        if (!$this->isCsrfTokenValid('reset_roles_' . $user->getId(), $request->request->get('_token'))) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_home');
        }

        $this->roleManager->resetRoles($user);

        $this->logger->log(
        'Réinitialisation rôles',
        $this->getUser(),
        $user->getName() . ' #' . $user->getId(),
        'Rôles réinitialisés par ' . $this->getUser()->getName()
        );
        
        $request->getSession()->getFlashBag()->clear();
        $this->addFlash('success', 'Les rôles de ' . $user->getName() . ' ont été réinitialisés.');

        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }



    /**
     * Affiche la liste de tous les utilisateurs pour les admins et modos.
     *
     * Vérifie que l’utilisateur connecté est staff (admin ou modo).
     * Sinon redirige selon les droits.
     *
     * @param UserRepository $userRepo Repository pour accéder aux utilisateurs.
     * @return Response Page affichant la liste des utilisateurs.
     */
    #[Route('/users', name: 'admin_users')]
    public function listUsers(UserRepository $userRepo): Response
    {
        if (!($this->uac->isStaff())) {
            return $this->uac->redirectingGlobal();
        }

        return $this->render('security/users.html.twig', [
            'users' => $userRepo->findAll(),
        ]);
    }

    /**
     * Affiche la liste de toutes les collections pour les admins et modos.
     *
     * Vérifie que l’utilisateur connecté est staff (admin ou modo).
     * Sinon redirige selon les droits.
     *
     * @param UserAccesChecker $uac Service de vérification des droits utilisateur.
     * @param ListeRepository $listeRepository Repository pour accéder aux collections.
     * @return Response Page affichant la liste des collections.
     */
    #[Route('/collections', name: 'admin_collections')]
    public function adminCollections(ListeRepository $listeRepository): Response
    {
        if (!($this->uac->isStaff())) {
            return $this->uac->redirectingGlobal();
        }
        return $this->render('security/collections.html.twig', [
            'collections' => $listeRepository->findAll(),
        ]);
    }
    
    /**
     * Affiche les logs administratifs triés par date décroissante.
     *
     * Accessible par les admins/modos uniquement (à vérifier avant appel).
     *
     * @param LogRepository $repo Repository pour accéder aux logs.
     * @return Response Page affichant la liste des logs.
     */
    #[Route('/logs', name: 'admin_logs')]
    public function showLogs(LogRepository $repo): Response
    {
        return $this->render('security/logs.html.twig', [
            'logs' => $repo->findBy([], ['date' => 'DESC']),
        ]);
    }

    /**
     * Affiche le hub administrateur avec accès rapide aux différentes sections.
     *
     * @return Response
     */
    #[Route('/hub', name: 'admin_hub')]
    public function adminHub(): Response
    {
        if (!$this->uac->isStaff()) {
            return $this->uac->redirectingGlobal();
        }

        return $this->render('security/hub_admin.html.twig',[
            'listes' => $this->listeRepository->findAll(),
            'collectionRaw' => array_map(fn($c) => ['date' => $c->getDateCreation()->format('Y-m-d H:i:s')],$this->listeRepository->findAll()),
            'collectionStats' => $this->listeRepository->countByCreationDate()
        ]);
    }

}
