<?php

// src/Controller/SitemapController.php

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Repository\ListeRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SitemapController extends AbstractController
{

    private ListeRepository $listeRepository;
    private UserRepository $userRepository;

    public function __construct(ListeRepository $listeRepository, UserRepository $userRepository)
    {
        $this->listeRepository = $listeRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('/sitemap', name: 'app_sitemap', requirements: ['_format' => 'html|xml'], format: 'xml')]
    public function index(Request $request): Response
    {
        $hostname = $request->getSchemeAndHttpHost();

        $urls = []; // Array to store sitemap URLs

        // Add your sitemap URLs dynamically (e.g., from database or routes)
    
        // Static URLs
        $urls[] = ['loc' => $this->generateUrl('app_home'), 'priority' => '1.00'];
        
        $urls[] = [
            'loc' => $this->generateUrl('app_collection'),
            'priority' => '1',
        ];
        $urls[] = [
            'loc' => $this->generateUrl('create_collection'),
            'priority' => '1',
        ];
        // Dynamic URLs from Post table 
        $listes = $this->listeRepository->findBy(['isVisible' => true]);
        foreach ($listes as $list) {
            $urls[] = [
                'loc' => $this->generateUrl('liste_show', [
                    'id' => $list->getId(), // ou 'slug' => $list->getSlug() si tu l'ajoutes
                ]),
                'priority' => '1.00',
                'lastmod' => $list->getDateCreation()?->format('Y-m-d')
            ];
        }
       
        $users = $this->userRepository->findAll(); // ou filtrer si nécessaire
        foreach ($users as $user) {
            $urls[] = [
                'loc' => $this->generateUrl('app_profil_visiteur', [
                    'id' => $user->getId(), // ou 'slug' si tu en as un
                ]),
                'priority' => '0.2',
            ];
        }

        $urls[] = [
            'loc' => $this->generateUrl('app_contact'),
            'priority' => '1',
        ];
        $urls[] = [
            'loc' => $this->generateUrl('app_terms_service'),
            'priority' => '0.3',
        ];
        $urls[] = [
            'loc' => $this->generateUrl('app_privacy_policy'),
            'priority' => '0.3',
        ];
        $urls[] = [
            'loc' => $this->generateUrl('app_legal_notice'),
            'priority' => '0.3',
        ];
        $urls[] = [
            'loc' => $this->generateUrl('app_login'),
            'priority' => '1',
        ];
        $urls[] = [
            'loc' => $this->generateUrl('app_register'),
            'priority' => '1',
        ];

        $xml = $this->renderView('sitemap/sitemap.xml.twig', [
                'urls' => $urls,
                'hostname' => $hostname
        ]);
        

        return new Response($xml, 200, ['Content-Type' => 'text/xml']);
    }
}