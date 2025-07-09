<?php

namespace App\Controller;

use App\Entity\Addon;
use App\Entity\Liste;
use App\Service\BlenderAPI;
use App\Service\AddonsManager;
use App\Service\AddonsScraper;
use App\Service\AddonDownloader;
use App\Repository\ListeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
            return $this->redirectToRoute('liste_show', [
                'id' => $liste->getId()
            ]);
        }

        return $this->render('collection/add.html.twig', []);
    }


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


    #[Route('/liste/{id}', name: 'liste_show')]
    public function show(int $id, ListeRepository $listeRepository): Response
    {
        $liste = $listeRepository->find($id);

        if (!$liste) {
            throw $this->createNotFoundException('Liste non trouvée.');
        }

        return $this->render('collection/detail.html.twig', [
            'liste' => $liste,
        ]);
    }

    #[Route('/liste/{id}/edit/name', name: 'update_liste_name', methods: ['POST'])]
    public function updateName(Liste $liste, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $name = $request->request->get('name');

        if ($name) {
            $liste->setName($name);
            $em->flush();
            $this->addFlash('success', 'Nom mis à jour avec succès.');
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    #[Route('/liste/{id}/edit/description', name: 'update_liste_description', methods: ['POST'])]
    public function updateDescription(Liste $liste, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $description = $request->request->get('description');

        $liste->setDescription($description);
        $em->flush();
        $this->addFlash('success', 'Description mise à jour avec succès.');

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    #[Route('/liste/{id}/edit/visibility', name: 'update_liste_visibility', methods: ['POST'])]
    public function updateVisibility(Liste $liste, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $isVisible = filter_var($request->request->get('isVisible'), FILTER_VALIDATE_BOOLEAN);

        $liste->setIsVisible($isVisible);
        $em->flush();
        $this->addFlash('success', 'Visibilité mise à jour avec succès.');

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    #[Route('/liste/{id}/remove-addon/{addonId}', name: 'remove_addon_from_liste', methods: ['POST'])]
    public function removeAddonFromListe(
        Liste $liste,
        int $addonId,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // CSRF protection
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('remove_addon_' . $addonId, $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $addon = $em->getRepository(Addon::class)->find($addonId);
        if (!$addon) {
            throw $this->createNotFoundException('Add-on introuvable.');
        }

        $liste->removeAddon($addon);
        $em->flush();

        $this->addFlash('success', 'Add-on retiré de la collection.');
        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    #[Route('/liste/{id}/add-addon', name: 'add_addon_to_liste', methods: ['POST'])]
    public function addAddonToListe(
        Liste $liste,
        Request $request,
        EntityManagerInterface $em,
        AddonsScraper $scraper
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add_addon', $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $url = trim($request->request->get('idBlender'));

        if (empty($url)) {
            $this->addFlash('error', 'Veuillez entrer une URL valide.');
            return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
        }

        try {
            $data = $scraper->getAddOn($url);

            if (empty($data['title']) || empty($data['tags']) || empty($data['size']) || empty($data['image'])) {
                $this->addFlash('error', 'L\'URL ne semble pas correspondre à un add-on valide.');
                return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
            }

            $addon = $em->getRepository(Addon::class)->findOneBy(['idBlender' => $url]);

            if (!$addon) {
                $addon = new Addon();
                $addon->setIdBlender($url);
                $em->persist($addon);
            }

            if (!$liste->getAddons()->contains($addon)) {
                $liste->addAddon($addon);
                $em->flush();
                $request->getSession()->getFlashBag()->clear();
                $this->addFlash('success', 'Add-on ajouté à la collection.');
            } else {
                $request->getSession()->getFlashBag()->clear();
                $this->addFlash('warning', 'Cet add-on est déjà présent dans la collection.');
            }

        } catch (\Throwable $e) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Impossible de valider l’URL : ' . $e->getMessage());
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    #[Route('/liste/{id}/download', name: 'liste_download_addons', methods: ['POST'])]
    public function downloadAddonsFromListe(Liste $liste, Request $request, AddonsScraper $scraper, BlenderAPI $blenderAPI, AddonDownloader $addonDownloader): BinaryFileResponse {

        if (!$this->isCsrfTokenValid('download_addons_' . $liste->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $urls = [];

        foreach ($liste->getAddons() as $addon) {
            $extension = $blenderAPI->findExtensionByWebsite($addon->getIdBlender());
            if ($extension && isset($extension['archive_url'])) {
                $urls[] = $extension['archive_url'];
            }
        }

        return $addonDownloader->downloadAndZip($urls, 'collection_' . $liste->getName() . '.zip');
    }



}
