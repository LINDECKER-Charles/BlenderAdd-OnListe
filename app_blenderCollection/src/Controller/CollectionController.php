<?php

namespace App\Controller;

use App\Service\AddonsScraper;
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
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if (!$this->getUser()->isVerified()) {
            /* Crée une page */ /* should_verify.html.twig */
            return $this->render('registration/should_verify.html.twig', []);
        }
        return $this->render('collection/add.html.twig', []);
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

    #[Route('/api/scrape-addon/', name: 'api_scrape_addon')]
    public function scrapeAddon(Request $request, AddonsScraper $scraper, SessionInterface $session): JsonResponse
    {
        $url = $request->query->get('url');

        if (!$url) {
            return $this->json(['error' => 'No URL provided'], 400);
        }

        try {
            $data = $scraper->getAddOn($url);
            // Récupération ou initialisation de la liste
            $addons = $session->get('valid_addons', []);

            $alreadyExists = false;
            foreach ($addons as $addon) {
                if (is_array($addon) && $addon[0] === $url) {
                    $alreadyExists = true;
                    break;
                }
            }

            // Si elle n'existe pas, on l’ajoute avec l’état `false`
            if (!$alreadyExists) {
                $addons[] = [$url, false];
                $session->set('valid_addons', $addons);
            }

            return $this->json($data);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Scraping failed'], 500);
        }
    }

    #[Route('/api/getAddOnSave', name: 'api_get_addon')]
    public function getAddOns(Request $request, SessionInterface $session): JsonResponse
    {
        $url = $request->query->get('url');
        $addons = $session->get('valid_addons', []);

        // 1. Marquer l'URL comme validée (true)
        foreach ($addons as $index => $addon) {
            if (isset($addon[0]) && $addon[0] === $url) {
                $addons[$index][1] = true;
                break;
            }
        }
        /* dd($addons); */
        // 2. Filtrer les addons validés
        $validated = array_filter($addons, function ($addon) {
            return isset($addon[1]) && $addon[1] === true;
        });

        // 3. Mettre à jour la session avec uniquement les validés
        $session->set('valid_addons', array_values($validated)); // Réindexation

        // 4. Gérer le cas où il n'y a rien à retourner
        if (empty($validated)) {
            return $this->json(['empty' => true]);
        }

        return $this->json(array_values($validated));
    }

    #[Route('/api/suprAddOnSave', name: 'api_supr_addon')]
    public function suprAddOns(Request $request, SessionInterface $session): JsonResponse
    {
        $url = $request->query->get('url');
        $addons = $session->get('valid_addons', []);

        // 1. Marquer l'URL comme validée (true)
        foreach ($addons as $index => $addon) {
            if (isset($addon[0]) && $addon[0] === $url) {
                $addons[$index][1] = false;
                break;
            }
        }
        /* dd($addons); */
        // 2. Filtrer les addons validés
        $validated = array_filter($addons, function ($addon) {
            return isset($addon[1]) && $addon[1] === true;
        });

        // 3. Mettre à jour la session avec uniquement les validés
        $session->set('valid_addons', array_values($validated)); // Réindexation

        // 4. Gérer le cas où il n'y a rien à retourner
        if (empty($validated)) {
            return $this->json(['empty' => true]);
        }


        return $this->json(array_values($validated));
    }





}
