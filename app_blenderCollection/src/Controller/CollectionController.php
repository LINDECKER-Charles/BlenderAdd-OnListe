<?php

namespace App\Controller;

use App\Entity\Addon;
use App\Entity\Liste;
use App\Service\AddonsManager;
use App\Service\AddonsScraper;
use App\Repository\ListeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class CollectionController extends AbstractController
{
    #[Route('/collection', name: 'app_collection')]
    public function index(ListeRepository $listeRepository): Response
    {
        $collections = $listeRepository->findBy(['isVisible' => true]);

        return $this->render('collection/index.html.twig', [
            'controller_name' => 'CollectionController',
            'collections' => $collections,
        ]);
    }
    
    #[Route('/collection/add', name: 'create_collection')]
    public function addCollection(Request $request, SluggerInterface $slugger, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user->isVerified()) {
            return $this->render('registration/should_verify.html.twig');
        }

        if ($request->isMethod('POST')) {
            $fullName = $request->request->get('fullName');
            $description = $request->request->get('description');
            $isVisible = $request->request->getBoolean('isVisible');

            /** @var UploadedFile|null $imageFile */
            $imageFile = $request->files->get('image');
            $imageFilename = null;

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $imageFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                $imageFile->move(
                    $this->getParameter('uploads_directory'),
                    $imageFilename
                );
            }

            $liste = new Liste();
            $liste->setName($fullName);
            $liste->setDescription($description);
            $liste->setImage($imageFilename);
            $liste->setUsser($user);
            $liste->setIsVisible($isVisible);
            $liste->setDateCreation(new \DateTime());

            // 🔄 Récupérer les add-ons validés dans la session
            $sessionAddons = $request->getSession()->get('valid_addons', []);

            foreach ($sessionAddons as [$url, $validated]) {
                if ($validated && isset($url)) {
                    // On cherche un Addon existant ou on le crée
                    $addon = $em->getRepository(Addon::class)->findOneBy(['idBlender' => $url]);

                    if (!$addon) {
                        $addon = new Addon();
                        $addon->setIdBlender($url);
                        $em->persist($addon);
                    }

                    $liste->addAddon($addon);
                }
            }

            $em->persist($liste);
            $em->flush();

            // Nettoyage session
            $request->getSession()->remove('valid_addons');

            $this->addFlash('success', 'La collection a bien été créée avec ses add-ons !');
            return $this->redirectToRoute('create_collection');
        }


        return $this->render('collection/add.html.twig', []);
    }

    /* A factoriser */
    #[Route('/api/scrape-addon/', name: 'api_scrape_addon')]
    public function scrapeAddon(AddonsManager $am, Request $request, AddonsScraper $scraper, SessionInterface $session): JsonResponse
    {
        $url = $request->query->get('url');

        if (!$url) {
            return $this->json(['error' => 'No URL provided'], 400);
        }

        try {
            $data = $scraper->getAddOn($url);
            $am->addStack($session, $data, $url);
            return $this->json($data);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Scraping failed'], 500);
        }
    }

    #[Route('/api/getAddOnSave', name: 'api_get_addon')]
    public function getAddOns(AddonsManager $am, Request $request, SessionInterface $session): JsonResponse
    {
        $url = $request->query->get('url');

        $addons = $am->addAddOn($url, $session);
        $validated = $am->cleanSession($addons, $session);

        if (empty($validated)) {
            return $this->json(['empty' => true]);
        }

        return $this->json(array_values($validated));
    }

    #[Route('/api/suprAddOnSave', name: 'api_supr_addon')]
    public function suprAddOns(AddonsManager $am, Request $request, SessionInterface $session): JsonResponse
    {
        $url = $request->query->get('url');

        $addons = $am->suprAddOn($url, $session);
        $validated = $am->cleanSession($addons, $session);

        if (empty($validated)) {
            return $this->json(['empty' => true]);
        }


        return $this->json(array_values($validated));
    }

}
