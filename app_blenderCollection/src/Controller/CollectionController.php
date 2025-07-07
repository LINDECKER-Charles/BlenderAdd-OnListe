<?php

namespace App\Controller;

use App\Service\AddonsScraper;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class CollectionController extends AbstractController
{
    #[Route('/collection', name: 'app_collection')]
    public function index(): Response
    {
        return $this->render('collection/index.html.twig', [
            'controller_name' => 'CollectionController',
        ]);
    }
    #[Route('/collection/add', name: 'create_collection')]
    public function addCollection(): Response
    {
        return $this->render('collection/add.html.twig', [
            
        ]);
    }

    #[Route('/collection/test', name: 'scraping_test')]
    public function testScraping(AddonsScraper $scraper): Response
    {
        $url = 'https://extensions.blender.org/add-ons/univ/';
        $data = $scraper->getAddOn($url);

        return $this->render('collection/test.html.twig', [
            'data' => $data,
            'url' => $url,
        ]);
    }

    #[Route('/api/scrape-addon/{save}', name: 'api_scrape_addon')]
    public function scrapeAddon(bool $save, Request $request, AddonsScraper $scraper, SessionInterface $session): JsonResponse
    {
        $url = $request->query->get('url');

        if (!$url) {
            return $this->json(['error' => 'No URL provided'], 400);
        }

        try {
            $data = $scraper->getAddOn($url);

            if($save){
                // Récupération ou initialisation de la liste
                $addons = $session->get('valid_addons', []);

                if (!in_array($url, $addons)) {
                    $addons[] = $url;
                    $session->set('valid_addons', $addons);
                }
            }

            return $this->json($data);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Scraping failed'], 500);
        }
    }




}
