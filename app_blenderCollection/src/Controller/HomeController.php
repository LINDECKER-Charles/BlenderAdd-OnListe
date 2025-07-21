<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ListeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class HomeController extends AbstractController
{
    /**
     * Affiche la page d’accueil du site.
     *
     * Accessible à tous les utilisateurs, connectés ou non.
     * Sert de point d’entrée principal avec liens vers les différentes sections.
     */
    #[Route('/', name: 'app_home')]
    public function index(
        ListeRepository $listeRepository,
    ): Response
    {

        return $this->render('home/index.html.twig', [
            'collections' => $listeRepository->findBy(['isVisible' => true]),
            'bg' => random_int(1, 4)
        ]);
    }

}

/* 
Pour ajouter un user en admin si non accès à postgre
        UserRepository $userRepo,
        EntityManagerInterface $em

        $user = $userRepo->find(1);

        if ($user && !in_array('ADMIN', $user->getRoles())) {
            $user->setRoles(array_unique([...$user->getRoles(), 'ADMIN']));
            $em->flush();
        }
*/