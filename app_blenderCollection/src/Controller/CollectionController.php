<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Addon;
use App\Entity\Liste;
use App\Entity\SousPost;
use App\Service\BlenderAPI;
use App\Service\AddonsManager;
use App\Service\AddonsScraper;
use App\Service\UploadManager;
use App\Service\AddonDownloader;
use App\Message\DeleteZipMessage;
use App\Service\UserAccesChecker;
use App\Message\DownloadZipMessage;
use App\Repository\ListeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class CollectionController extends AbstractController
{
    /* index.html.twig */
    /**
     * Affiche toutes les collections publiques.
     *
     * Fonctionnalités incluses :
     * - Récupération des collections dont la visibilité est activée (`isVisible = true`)
     * - Passage des collections à la vue Twig pour affichage
     *
     * @param ListeRepository $listeRepository Le repository permettant de récupérer les collections
     * @return Response
     */
    #[Route('/collection', name: 'app_collection')]
    public function index(ListeRepository $listeRepository): Response
    {
        return $this->render('collection/index.html.twig', [
            'controller_name' => 'CollectionController',
            'collections' => $listeRepository->findBy(['isVisible' => true]),
        ]);
    }
    

    /* add.html.twig */

    #[Route('/collection/add', name: 'create_collection')]
    public function addCollection(RateLimiterFactory $collectionLimiter, UserAccesChecker $uac, Request $request, EntityManagerInterface $em, UploadManager $uploadManager, AddonsScraper $scraper, MessageBusInterface $bus, AddonDownloader $addonDownloader): Response
    {
        //On verifie que l'utilisateur est loger et verifier
        $user = $this->getUser();
        if (!$uac->isVerified($user)) {
            return $uac->redirectingGlobal($user);
        }


        if ($request->isMethod('POST')) {

            /* Verification si absence d'add-on ou si spamm */
            $limiter = $collectionLimiter->create($request->getClientIp());
            $limit = $limiter->consume();
            $sessionAddons = $request->getSession()->get('valid_addons', []);
            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter();

                $seconds = $retryAfter ? $retryAfter->getTimestamp() - time() : 60;

                $minutes = ceil($seconds / 60);
                $message = "Trop de tentatives. Réessayez dans environ $minutes minute" . ($minutes > 1 ? 's' : '') . ".";

                $request->getSession()->getFlashBag()->clear();
                $this->addFlash('error', $message);

                return $uac->redirectingGlobal($user);
            }else if (empty($sessionAddons)) {
                $request->getSession()->getFlashBag()->clear();
                $this->addFlash('error', 'Impossible de crée une collection sans add-on.');
                return $uac->redirectingGlobal($user);
            }else if(count($sessionAddons) > 50){
                $request->getSession()->getFlashBag()->clear();
                $this->addFlash('error', 'Vous ne pouvez pas ajouter plus de 50 add-ons à une collection.');
                return $uac->redirectingGlobal($user);
            } 
            $fullName = $request->request->get('fullName');
            $existing = $em->getRepository(Liste::class)->findOneBy(['name' => $fullName]);
            if ($existing) {
                $request->getSession()->getFlashBag()->clear();
                $this->addFlash('error', 'Une collection porte déjà ce nom. Choisissez-en un autre.');
                return $uac->redirectingGlobal($user);
            }


            $description = $request->request->get('description');
            $isVisible = $request->request->getBoolean('isVisible');

            //Creation de la liste
            $liste = new Liste();
            $liste->setName($fullName);
            $liste->setDescription($description);

            // Recuperer les add-ons valides dans la session

            /** @var UploadedFile|null $imageFile */
            $imageFile = $request->files->get('image');
            $imageFilename = null;
            if ($imageFile) {
                $imageFilename = $uploadManager->uploadLocalFile($imageFile);
            } elseif (!empty($sessionAddons)) {
                $imageUrl = $scraper->getAddOnImage($sessionAddons[0][0]);
                if ($imageUrl) {
                    $imageFilename = $uploadManager->uploadFromUrl($imageUrl);
                }
            }

            if ($imageFilename) {
                $liste->setImage($imageFilename);
            }
            
            $liste->setUsser($user);
            $liste->setIsVisible($isVisible);
            $liste->setDateCreation(new \DateTime());
            $liste->setDownload(0);

            foreach ($sessionAddons as [$url]) {
                if (isset($url)) {
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
            $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';

            // Envoie du message asynchrone
            $addonUrls = array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray());
            $urls = $addonDownloader->resolveArchiveUrls($addonUrls);
            $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';
            $bus->dispatch(new DownloadZipMessage($urls, $zipName));

            $em->flush();

            $request->getSession()->remove('valid_addons');
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('success', 'La collection a bien été créée avec ses add-ons !');

            return $this->redirectToRoute('liste_show', [
                'id' => $liste->getId()
            ]);
        }

        return $this->render('collection/add.html.twig', []);
    }

    /* Rajouter un add-on dans la session */
    /**
     * API pour ajouter un add-on à la session.
     *
     * Vérifie que l'URL est fournie et conforme, puis scrape les données associées
     * à l'add-on et l'ajoute à la session utilisateur.
     *
     * @param Request $request Requête contenant l'URL de l'add-on
     * @param AddonsScraper $scraper Service pour récupérer les données d’un add-on
     * @param AddonsManager $am Service pour gérer l’ajout en session
     * @param SessionInterface $session Session utilisateur
     *
     * @return JsonResponse Réponse JSON contenant le succès ou une erreur
     */
    #[Route('/api/add-addon', name: 'api_add_addon', methods: ['POST'])]
    public function addAddon(UserAccesChecker $uac, Request $request, AddonsScraper $scraper, AddonsManager $am, SessionInterface $session): JsonResponse {

        if (!$uac->isConnected()) {
            return $uac->redirectingGlobalJson();
        }

        $url = $request->request->get('url');

        if (!$url) {
            return $this->json(['error' => 'Aucune URL fournie'], 400);
        } else if (!$am->isValidAddonUrl($url)) {
            return $this->json(['error' => 'URL non valide'], 400);
        }

        try {
            $am->addAddOn($url, $session);
            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Scraping échoué'], 500);
        }
    }
    /**
     * API pour retirer un add-on de la session.
     *
     * Supprime l’add-on correspondant à l’URL reçue de la session utilisateur.
     *
     * @param Request $request Requête contenant l’URL de l’add-on à supprimer
     * @param AddonsManager $am Service de gestion des add-ons
     * @param SessionInterface $session Session utilisateur
     *
     * @return JsonResponse Réponse JSON confirmant la suppression
     */
    #[Route('/api/remove-addon', name: 'api_remove_addon', methods: ['POST'])]
    public function removeAddon(UserAccesChecker $uac, Request $request, AddonsManager $am, SessionInterface $session): JsonResponse {
        
        if (!$uac->isConnected()) {
            return $uac->redirectingGlobalJson();
        }

        $url = $request->request->get('url');

        if (!$url) {
            return $this->json(['error' => 'Aucune URL fournie'], 400);
        }

        $am->suprAddOn($url, $session);

        return $this->json(['success' => true]);
    }
    /**
    * API pour récupérer tous les add-ons actuellement stockés dans la session.
    *
    * Retourne un tableau JSON contenant les add-ons ou une clé 'empty' si la session est vide.
    *
    * @param SessionInterface $session Session utilisateur
    *
    * @return JsonResponse Liste des add-ons en session
    */
    #[Route('/api/get-session-addons', name: 'api_get_session_addons')]
    public function getSessionAddons(UserAccesChecker $uac, SessionInterface $session): JsonResponse
    {
        if (!$uac->isConnected()) {
            return $uac->redirectingGlobalJson();
        }
        
        $addons = $session->get('valid_addons', []);
        if (empty($addons)) {
            return $this->json(['empty' => true]);
        }

        return $this->json(array_values($addons));
    }


    /* detail.html.twig */
        /* Affichage de la liste */
    /**
     * Affiche le détail d’une liste (collection) et ses posts associés.
     *
     * Vérifie si l'utilisateur est connecté, puis charge la liste par son ID.
     * En cas d'absence, une 404 est levée.
     *
     * @param UserAccesChecker     $uac              Service de contrôle d'accès utilisateur
     * @param int                  $id               ID de la liste à afficher
     * @param ListeRepository      $listeRepository  Repository des listes
     *
     * @return Response Vue détaillée de la liste
     */
    #[Route('/liste/{id}', name: 'liste_show')]
    public function show(UserAccesChecker $uac, int $id, ListeRepository $listeRepository): Response
    {
        /* On verifie que l'utilisateur est connecté */
        if (!$uac->isConnected()) {
            return $uac->redirectingGlobal();
        }
        $liste = $listeRepository->find($id);

        if (!$liste) {
            throw $this->createNotFoundException('Liste non trouvée.');
        }

        return $this->render('collection/detail.html.twig', [
            'liste' => $liste,
            'posts' => $liste->getPosts(),
        ]);
    }

        /* Edition d'une collection */
    /**
     * Met à jour le nom d'une collection si l'utilisateur est autorisé.
     *
     * - Vérifie que l'utilisateur est soit propriétaire, soit membre du staff.
     * - Refuse le changement si une autre collection possède déjà ce nom.
     * - Supprime l'ancienne archive ZIP associée à l'ancien nom (asynchrone).
     * - Met à jour le nom dans la base de données.
     * - Recrée une nouvelle archive ZIP avec le nouveau nom (asynchrone).
     * - Affiche un message flash selon le résultat.
     *
     * @param UserAccesChecker      $uac        Vérifie les permissions d'accès
     * @param Liste                 $liste      Entité de la collection ciblée
     * @param Request               $request    Requête HTTP contenant le nouveau nom
     * @param EntityManagerInterface $em        Pour persister les changements
     * @param MessageBusInterface   $bus        Bus Messenger pour lancer les tâches asynchrones
     * @param AddonDownloader       $downloader Service de gestion des fichiers ZIP (non utilisé ici mais injectable si besoin)
     *
     * @return Response
     */
    #[Route('/liste/{id}/edit/name', name: 'update_liste_name', methods: ['POST'])]
    public function updateName(UserAccesChecker $uac, Liste $liste, Request $request, EntityManagerInterface $em, MessageBusInterface $bus, AddonDownloader $downloader): Response
    {
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $newName = $request->request->get('name');

        if ($newName) {
            // Vérifie que le nom n'est pas déjà pris par une autre collection
            $existing = $em->getRepository(Liste::class)->findOneBy(['name' => $newName]);
            if ($existing && $existing->getId() !== $liste->getId()) {
                $this->addFlash('error', 'Ce nom est déjà utilisé pour une autre collection.');
                return $uac->redirectingGlobal();
            }

            // Supprime l’ancienne archive
            $oldZipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';
            $bus->dispatch(new DeleteZipMessage($oldZipName));

            // Met à jour le nom
            $liste->setName($newName);
            $em->flush();

            // Recrée une archive avec le nouveau nom
            // Envoie du message asynchrone
            $addonUrls = array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray());
            $urls = $downloader->resolveArchiveUrls($addonUrls);
            $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';
            $bus->dispatch(new DownloadZipMessage($urls, $zipName));

            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('success', 'Nom mis à jour avec succès.');
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }
    /**
     * Met à jour la description d’une liste.
     *
     * Accessible uniquement au propriétaire ou aux membres du staff.
     * Enregistre la nouvelle description sans validation spécifique.
     *
     * @param UserAccesChecker        $uac    Vérification des droits d’accès
     * @param Liste                   $liste  Liste à modifier
     * @param Request                 $request Requête contenant la description
     * @param EntityManagerInterface  $em     Gestionnaire d’entité Doctrine
     *
     * @return Response Redirection vers la vue de la liste
     */
    #[Route('/liste/{id}/edit/description', name: 'update_liste_description', methods: ['POST'])]
    public function updateDescription(UserAccesChecker $uac, Liste $liste, Request $request, EntityManagerInterface $em): Response
    {
        /* On verifie qu'il est bien proprietaire de la liste ou membre du staff */
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }


        $description = $request->request->get('description');

        $liste->setDescription($description);
        $em->flush();
        $this->addFlash('success', 'Description mise à jour avec succès.');

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }
    /**
     * Met à jour la visibilité publique d’une liste.
     *
     * Seul le propriétaire ou un membre du staff peut changer cet état.
     * La valeur booléenne est extraite et validée à partir de la requête.
     *
     * @param UserAccesChecker        $uac    Vérification des droits d’accès
     * @param Liste                   $liste  Liste à modifier
     * @param Request                 $request Requête contenant le champ `isVisible`
     * @param EntityManagerInterface  $em     Gestionnaire d’entité Doctrine
     *
     * @return Response Redirection vers la vue de la liste
     */
    #[Route('/liste/{id}/edit/visibility', name: 'update_liste_visibility', methods: ['POST'])]
    public function updateVisibility(UserAccesChecker $uac, Liste $liste, Request $request, EntityManagerInterface $em): Response
    {
        /* On verifie qu'il est bien proprietaire de la liste ou membre du staff */
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }


        $isVisible = filter_var($request->request->get('isVisible'), FILTER_VALIDATE_BOOLEAN);

        $liste->setIsVisible($isVisible);
        $em->flush();
        $this->addFlash('success', 'Visibilité mise à jour avec succès.');

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }
        /**
     * Met à jour l'image d’une liste via un fichier envoyé par formulaire.
     *
     * Seuls le propriétaire ou un membre du staff peuvent effectuer cette opération.
     * Utilise le service UploadManager pour gérer l’enregistrement local du fichier.
     * Gère les erreurs si le fichier est invalide ou non traité.
     *
     * @param UploadManager           $uploadManager Service de gestion d’upload de fichiers
     * @param UserAccesChecker        $uac            Vérification des droits d’accès
     * @param Request                 $request        Requête contenant le fichier image
     * @param Liste                   $liste          Liste cible
     * @param EntityManagerInterface  $em             Gestionnaire d’entité Doctrine
     * @param SluggerInterface        $slugger        (Non utilisé ici, présent pour compatibilité)
     *
     * @return Response Redirection vers la vue de la liste
     */
    #[Route('/liste/{id}/update-image', name: 'liste_update_image', methods: ['POST'])]
    public function updateImage(UploadManager $uploadManager, UserAccesChecker $uac, Request $request, Liste $liste, EntityManagerInterface $em, SluggerInterface $slugger): Response {
        /* On verifie qu'il est bien proprietaire de la liste ou membre du staff */
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }


        $image = $request->files->get('image');

    if ($image && $image->isValid()) {
        // Utilisation du service UploadManager
        $filename = $uploadManager->uploadLocalFile($image);

        if ($filename !== null) {
            $liste->setImage($filename);
            $em->flush();
        } else {
            $this->addFlash('error', 'Le fichier n’a pas pu être traité. Format non autorisé.');
        }
    } else {
        $this->addFlash('error', 'Image invalide ou manquante.');
    }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    /**
     * Supprime une collection si l'utilisateur en a les droits, ainsi que son archive ZIP associée.
     *
     * - Vérifie les droits d'accès (propriétaire ou staff)
     * - Valide le token CSRF
     * - Supprime l'entité en base
     * - Lance en asynchrone la suppression de l'archive ZIP liée
     * - Ajoute un message flash selon le succès ou l'échec
     *
     * @param UserAccesChecker       $uac   Vérifie les permissions
     * @param Request                $request Requête contenant le token CSRF
     * @param Liste                  $liste La collection à supprimer
     * @param EntityManagerInterface $em    Pour supprimer en base
     * @param MessageBusInterface    $bus   Pour exécuter la suppression du ZIP en tâche de fond
     *
     * @return Response
     */
    #[Route('/collection/delete/{id}', name: 'delete_collection', methods: ['POST'])]
    public function deleteListe(UserAccesChecker $uac, Request $request, Liste $liste, EntityManagerInterface $em, MessageBusInterface $bus): Response
    {
        /* On verifie qu'il est bien proprietaire de la liste ou membre du staff */
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $formToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_collection_' . $liste->getId(), $formToken)) {
            try {
                $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';
                $bus->dispatch(new DeleteZipMessage($zipName));
                $em->remove($liste);
                $em->flush();

                $this->addFlash('success', 'Collection supprimée avec succès.');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_collection');
    }

    /**
     * Retire un add-on d'une collection.
     *
     * Vérifie les droits d'accès et la validité du token CSRF.
     * L'add-on est ensuite retiré de la collection et la base de données mise à jour.
     *
     * @param UserAccesChecker       $uac     Contrôle d'accès
     * @param Liste                  $liste   Collection ciblée
     * @param int                    $addonId ID de l'add-on à retirer
     * @param EntityManagerInterface $em      Gestionnaire Doctrine
     * @param Request                $request Requête contenant le token CSRF
     *
     * @return Response Redirection vers la page de la liste
     */
    #[Route('/liste/{id}/remove-addon/{addonId}', name: 'remove_addon_from_liste', methods: ['POST'])]
    public function removeAddonFromListe(UserAccesChecker $uac, Liste $liste, int $addonId, EntityManagerInterface $em, Request $request, MessageBusInterface $bus, AddonDownloader $downloader): Response {
        /* On verifie qu'il est bien proprietaire de la liste ou membre du staff */
        if ((!$uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

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
        
        // Recrée l’archive ZIP avec les add-ons restants
        $addonUrls = array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray());
        $urls = $downloader->resolveArchiveUrls($addonUrls);
        $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';
        $bus->dispatch(new DownloadZipMessage($urls, $zipName));

        $request->getSession()->getFlashBag()->clear();
        $this->addFlash('success', 'Add-on retiré de la collection.');
        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }
    /**
     * Ajoute un add-on à une collection à partir d'une URL.
     *
     * Vérifie les droits d'accès, la validité du token CSRF, et tente de scraper les données de l'add-on.
     * Si l'add-on n'existe pas, il est créé et associé à la collection.
     *
     * @param UserAccesChecker       $uac     Contrôle d'accès
     * @param Liste                  $liste   Collection cible
     * @param Request                $request Requête contenant l'URL et le token CSRF
     * @param EntityManagerInterface $em      Gestionnaire Doctrine
     * @param AddonsScraper          $scraper Service de scraping d'add-ons
     *
     * @return Response Redirection vers la page de la liste
     */
    #[Route('/liste/{id}/add-addon', name: 'add_addon_to_liste', methods: ['POST'])]
    public function addAddonToListe(UserAccesChecker $uac, Liste $liste, Request $request, EntityManagerInterface $em, AddonsScraper $scraper, MessageBusInterface $bus, AddonDownloader $downloader): Response {
        /* On verifie qu'il est bien proprietaire de la liste ou membre du staff */
        if ((!$uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

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

                // Nettoie les messages précédents
                $request->getSession()->getFlashBag()->clear();

                // Recrée l’archive ZIP
                $addonUrls = array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray());
                $urls = $downloader->resolveArchiveUrls($addonUrls);
                $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';
                $bus->dispatch(new DownloadZipMessage($urls, $zipName));

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

    /**
     * Télécharge tous les add-ons d'une collection sous forme d'archive ZIP.
     *
     * Vérifie le token CSRF, récupère les URLs des archives à partir de l'API Blender,
     * puis utilise le service d’archivage pour générer le fichier ZIP.
     * Incrémente le compteur de téléchargements de la collection.
     *
     * @param UserAccesChecker       $uac            Contrôle d'accès
     * @param Liste                  $liste          Collection ciblée
     * @param Request                $request        Requête contenant le token CSRF
     * @param BlenderAPI             $blenderAPI     API Blender pour retrouver les URLs
     * @param AddonDownloader        $addonDownloader Service de téléchargement et archivage
     * @param EntityManagerInterface $em             Gestionnaire Doctrine
     *
     * @return BinaryFileResponse Archive ZIP des add-ons
     */
    #[Route('/liste/{id}/download', name: 'liste_download_addons', methods: ['POST'])]
    public function downloadAddonsFromListe(UserAccesChecker $uac, Liste $liste, Request $request, BlenderAPI $blenderAPI, AddonDownloader $addonDownloader, EntityManagerInterface $em): BinaryFileResponse {

        if (!$this->isCsrfTokenValid('download_addons_' . $liste->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $addonUrls = array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray());
        $urls = $addonDownloader->resolveArchiveUrls($addonUrls);

        if(!$liste->getDownload()){
            $liste->setDownload(1);
        }else{
            $liste->setDownload($liste->getDownload() + 1);
        }
        $em->persist($liste);
        $em->flush();
        return $addonDownloader->downloadAndZip($urls, preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo('collection_' . $liste->getName(), PATHINFO_FILENAME)) . '.zip');
    }

    /**
     * Ajoute un commentaire à une collection.
     *
     * Vérifie que l'utilisateur est connecté et vérifié, puis associe le commentaire à l'utilisateur et à la collection.
     * Gère la protection CSRF.
     *
     * @param UserAccesChecker       $uac       Contrôle d'accès
     * @param Liste                  $liste     Collection commentée
     * @param Request                $request   Requête contenant le contenu et le token CSRF
     * @param EntityManagerInterface $em        Gestionnaire Doctrine
     * @param Security               $security  Service de sécurité pour récupérer l'utilisateur connecté
     *
     * @return Response Redirection vers la page de la liste
     */
    #[Route('/liste/{id}/comment', name: 'liste_comment', methods: ['POST'])]
    public function addComment(UserAccesChecker $uac, Liste $liste, Request $request, EntityManagerInterface $em, Security $security): Response {

        /* On verifie qu'il est bien connecté et vérifié */
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        $content = trim($request->request->get('content'));

        if (!$this->isCsrfTokenValid('add_comment_' . $liste->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($content) {
            $post = new Post();
            $post->setContent($content);
            $post->setDateCreation(new \DateTime());
            $post->setCommentaire($liste);

            $user = $security->getUser();
            if ($user) {
                $post->setCommenter($user);
            }

            $em->persist($post);
            $em->flush();
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }
    /**
     * Répond à un commentaire existant (Post).
     *
     * Vérifie que l'utilisateur est connecté et vérifié, puis enregistre une réponse (SousPost).
     * Protégé contre les attaques CSRF.
     *
     * @param UserAccesChecker       $uac     Contrôle d'accès
     * @param Post                   $post    Commentaire parent
     * @param Request                $request Requête contenant le contenu et le token CSRF
     * @param EntityManagerInterface $em      Gestionnaire Doctrine
     *
     * @return Response Redirection vers la page de la collection associée
     */
    #[Route('/post/{id}/reply', name: 'post_reply', methods: ['POST'])]
    public function replyToPost(UserAccesChecker $uac, Post $post, Request $request, EntityManagerInterface $em): Response {
        /* On verifie qu'il est bien connecté et vérifié */
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        $content = trim($request->request->get('content'));

        if (!$this->isCsrfTokenValid('reply_' . $post->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($content) {
            $reply = new SousPost();
            $reply->setContent($content);
            $reply->setDateCreation(new \DateTime());
            $reply->setPost($post);
            $reply->setCommenter($this->getUser());

            $em->persist($reply);
            $em->flush();
        }

        return $this->redirectToRoute('liste_show', ['id' => $post->getCommentaire()->getId()]);
    }
    /**
     * Supprime un commentaire (Post) si l'utilisateur est autorisé.
     *
     * Vérifie la propriété du commentaire ou les droits de staff.
     * Protégé par un token CSRF.
     *
     * @param UserAccesChecker       $uac     Contrôle d'accès
     * @param Post                   $post    Commentaire à supprimer
     * @param Request                $request Requête contenant le token CSRF
     * @param EntityManagerInterface $em      Gestionnaire Doctrine
     *
     * @return Response Redirection vers la page de la liste associée
     */
    #[Route('/post/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function deletePost(UserAccesChecker $uac, Post $post, Request $request, EntityManagerInterface $em): Response
    {
        /* On verifie qu'il est bien proprietaire du Post ou membre du staff */
        if (!($uac->isOwnerOfPost($post) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        if ($this->isCsrfTokenValid('delete_post_' . $post->getId(), $request->request->get('_token'))) {
            $em->remove($post);
            $em->flush();
        }

        return $this->redirectToRoute('liste_show', ['id' => $post->getCommentaire()->getId()]);
    }

    /**
     * Supprime une réponse à un commentaire (SousPost).
     *
     * Vérifie la propriété de la réponse ou les droits de staff.
     * Protégé par un token CSRF.
     *
     * @param UserAccesChecker       $uac       Contrôle d'accès
     * @param SousPost               $sousPost  Réponse à supprimer
     * @param Request                $request   Requête contenant le token CSRF
     * @param EntityManagerInterface $em        Gestionnaire Doctrine
     *
     * @return Response Redirection vers la page de la collection associée
     */
    #[Route('/souspost/{id}/delete', name: 'souspost_delete', methods: ['POST'])]
    public function deleteSousPost(UserAccesChecker $uac, SousPost $sousPost, Request $request, EntityManagerInterface $em): Response
    {
        /* On verifie qu'il est bien proprietaire du SousPost ou membre du staff */
        if (!($uac->isOwnerOfSousPost($sousPost) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        if ($this->isCsrfTokenValid('delete_souspost_' . $sousPost->getId(), $request->request->get('_token'))) {
            $em->remove($sousPost);
            $em->flush();
        }

        return $this->redirectToRoute('liste_show', ['id' => $sousPost->getPost()->getCommentaire()->getId()]);
    }

    /**
     * Like ou retire un like d’un commentaire (Post).
     *
     * Vérifie que l'utilisateur est connecté et vérifié. Si le like existe déjà, il est retiré.
     * Sinon, l'utilisateur est ajouté à la liste des personnes ayant aimé le commentaire.
     *
     * @param UserAccesChecker       $uac     Contrôle d'accès
     * @param Post                   $post    Commentaire ciblé
     * @param EntityManagerInterface $em      Gestionnaire Doctrine
     *
     * @return Response Redirection vers la page de la collection avec ancre sur le commentaire
     */
    #[Route('/post/{id}/like', name: 'post_like', methods: ['POST'])]
    public function likePost(UserAccesChecker $uac, Post $post, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        if ($post->getLiker()->contains($user)) {
            $post->removeLiker($user);
        } else {
            $post->addLiker($user);
        }

        $em->flush();

        return $this->redirect($this->generateUrl('liste_show', ['id' => $post->getCommentaire()->getId()]) . '#post-' . $post->getId());
    }
    /**
     * Like ou retire un like d’une réponse (SousPost).
     *
     * Vérifie que l'utilisateur est connecté et vérifié. Bascule l'état du like sur le sous-commentaire.
     *
     * @param UserAccesChecker       $uac       Contrôle d'accès
     * @param SousPost               $sousPost  Réponse ciblée
     * @param EntityManagerInterface $em        Gestionnaire Doctrine
     *
     * @return Response Redirection vers la page de la collection avec ancre sur le commentaire parent
     */
    #[Route('/souspost/{id}/like', name: 'souspost_like', methods: ['POST'])]
    public function likeSousPost(UserAccesChecker $uac, SousPost $sousPost, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        if ($sousPost->getLikes()->contains($user)) {
            $sousPost->removeLike($user);
        } else {
            $sousPost->addLike($user);
        }

        $em->flush();

        return $this->redirect($this->generateUrl('liste_show', ['id' => $sousPost->getPost()->getCommentaire()->getId()]) . '#post-' . $sousPost->getPost()->getId());
    }

    /**
     * Ajoute ou retire une collection (liste) des favoris de l'utilisateur connecté.
     *
     * Vérifie que l'utilisateur est connecté et vérifié. Bascule l'état de favori de la liste.
     * Redirige vers la page de la collection concernée.
     *
     * @param UserAccesChecker       $uac      Contrôle d'accès
     * @param Liste                  $liste    Collection ciblée
     * @param EntityManagerInterface $em       Gestionnaire Doctrine
     * @param Security               $security Service de sécurité pour récupérer l'utilisateur
     *
     * @return RedirectResponse Redirection vers la page de la liste
     */
    #[Route('/liste/{id}/toggle-favoris', name: 'toggle_favoris')]
    public function toggleFavoris(UserAccesChecker $uac, Liste $liste, EntityManagerInterface $em, Security $security): RedirectResponse
    {
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getFavoris()->contains($liste)) {
            $user->removeFavori($liste);
        } else {
            $user->addFavori($liste);
        }

        $em->flush();

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

}
