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
use App\Service\Collection\CollectionEditor;
use App\Service\Collection\CollectionCreator;
use App\Service\Collection\CollectionRenamer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\Collection\CollectionDownloader;
use App\Service\Collection\CollectionRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\String\Slugger\SluggerInterface;
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
    public function index(
        ListeRepository $listeRepository
        ): Response
    {
        return $this->render('collection/index.html.twig', [
            'collections' => $listeRepository->findBy(['isVisible' => true]),
        ]);
    }
    

    /* add.html.twig */
    /**
     * Gère la création d’une nouvelle collection d’add-ons par l’utilisateur connecté.
     *
     * Cette méthode :
     * - Vérifie si l'utilisateur est connecté et vérifié (via UserAccesChecker)
     * - Applique une limitation de fréquence (rate limiter) par adresse IP
     * - Si le formulaire est soumis, délègue la création métier au service CollectionCreator
     * - En cas de succès, redirige vers la page de la collection avec un message flash
     * - En cas d’échec (limite atteinte ou erreur métier), affiche un message d’erreur et redirige
     *
     * @param CollectionRateLimiter $rateLimiter Service de limitation des tentatives
     * @param UserAccesChecker $uac Vérification des droits d’accès utilisateur
     * @param Request $request Requête HTTP courante
     * @param CollectionCreator $creator Service métier de création de collection
     * @return Response
     */
    #[Route('/collection/add', name: 'create_collection')]
    public function addCollection(
        CollectionRateLimiter $rateLimiter, 
        UserAccesChecker $uac, 
        Request $request, 
        CollectionCreator $creator
        ): Response {
        $user = $this->getUser();
        if (!$uac->isVerified($user) || !$uac->isAllowed($user)) {
            return $uac->redirectingGlobal($user);
        }

        if ($request->isMethod('POST')) {
            $limit = $rateLimiter->consume($request);

            if (!$limit->isAccepted()) {
                $this->addFlash('error', $rateLimiter->getErrorMessage($limit));
                return $uac->redirectingGlobal($user);
            }

            try {
                $liste = $creator->handle($request, $user);
                $this->addFlash('success', 'La collection a bien été créée avec ses add-ons !');
                return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
                return $uac->redirectingGlobal($user);
            }
        }

        return $this->render('collection/add.html.twig');
    }

    /* API */
    
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
    #[Route('/api/add-addon', name: 'api_add_addon', methods: ['GET', 'POST'])]
    public function addAddon(
        UserAccesChecker $uac, 
        Request $request, 
        AddonsManager $am, 
        SessionInterface $session
        ): JsonResponse {

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
    #[Route('/api/remove-addon', name: 'api_remove_addon', methods: ['GET', 'POST'])]
    public function removeAddon(
        UserAccesChecker $uac, 
        Request $request, 
        AddonsManager $am, 
        SessionInterface $session
        ): JsonResponse {
        
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
    *
    * @psalm-taint-escape ssrf $url
    */
    #[Route('/api/get-session-addons', name: 'api_get_session_addons', methods: ['GET', 'POST'])]
    public function getSessionAddons(
        UserAccesChecker $uac, 
        SessionInterface $session
        ): JsonResponse
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

    #[Route('/api/addon', name: 'api_get_addon', methods: ['GET', 'POST'])]
    public function getAddons(
        UserAccesChecker $uac,
        SessionInterface $session,
        Request $request,
        AddonsScraper $scrp,
        AddonsManager $am
    ): JsonResponse {
        if (!$uac->isConnected()) {
            return $uac->redirectingGlobalJson();
        }

        parse_str($request->getContent(), $params);
        $url = trim($params['url'] ?? '');
        // $url est validée par isValidAddonUrl() pour éviter les attaques SSRF (schéma, domaine, chemin, IP)
        if (!$url || !$am->isValidAddonUrl($url)) {
            if(!$am->isValidAddonUrl($url)){
                return $this->json(['empty' => 'validation url' . $am->isValidAddonUrl($url) . $url]);
            }
            return $this->json(['empty' => $url]);
        }

        return $this->json($scrp->getAddOn($url));
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
    public function show(
        UserAccesChecker $uac, 
        int $id, 
        ListeRepository $listeRepository
        ): Response
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
        ]);
    }

        /* Edition d'une collection */
    /**
     * Met à jour le nom d'une collection si l'utilisateur est autorisé.
     *
     * Cette méthode délègue la logique métier complète à un service dédié (`CollectionRenamer`) qui :
     * - Sanitize le nom reçu pour prévenir les attaques XSS ou les contenus malveillants.
     * - Vérifie que le nom est unique (hors collection actuelle).
     * - Supprime l’ancienne archive ZIP (via Messenger).
     * - Met à jour le nom de la collection en base de données.
     * - Recrée une archive ZIP avec le nouveau nom (via Messenger).
     * - Ajoute un message flash selon le succès ou l’échec de l’opération.
     *
     * @param UserAccesChecker $uac        Vérifie si l'utilisateur est propriétaire ou staff
     * @param Liste            $liste      La collection à renommer
     * @param Request          $request    La requête contenant le nouveau nom
     * @param CollectionEditor $renamer   Service métier chargé du renommage sécurisé
     *
     * @return Response Redirige vers la vue de la collection avec message flash
     */
    #[Route('/liste/{id}/edit/name', name: 'update_liste_name', methods: ['POST'])]
    public function updateName(
        UserAccesChecker $uac, 
        Liste $liste, 
        Request $request, 
        CollectionEditor $editor
        ): Response {
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        try {
            $editor->updateName($liste, $request);
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('success', 'Nom mis à jour avec succès.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    /**
     * Met à jour la description d’une collection.
     *
     * - Accessible uniquement au propriétaire de la collection ou à un membre du staff.
     * - Délègue la logique métier à CollectionEditor pour :
     *     → Nettoyer la nouvelle description (strip_tags).
     *     → Mettre à jour l’entité Liste et persister les changements.
     * - Affiche un message flash de succès si tout se passe bien.
     *
     * @param UserAccesChecker  $uac     Vérifie les permissions d'accès
     * @param Liste             $liste   Collection ciblée
     * @param Request           $request Requête contenant la nouvelle description
     * @param CollectionEditor  $editor  Service métier gérant les modifications de collection
     *
     * @return Response Redirection vers la page de la collection
     */
    #[Route('/liste/{id}/edit/description', name: 'update_liste_description', methods: ['POST'])]
    public function updateDescription(
        UserAccesChecker $uac, 
        Liste $liste, 
        Request $request, 
        CollectionEditor $editor
        ): Response
    {
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $editor->updateDescription($liste, $request);
        $this->addFlash('success', 'Description mise à jour avec succès.');

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    /**
     * Met à jour la visibilité d’une collection.
     *
     * - Accessible uniquement au propriétaire ou aux membres du staff.
     * - La valeur est convertie en booléen avec `FILTER_VALIDATE_BOOLEAN`.
     * - La modification est persistée.
     * - Un message flash confirme la réussite de l’opération.
     *
     * @param UserAccesChecker  $uac     Vérifie les permissions d'accès
     * @param Liste             $liste   Collection à modifier
     * @param Request           $request Requête contenant le champ `isVisible`
     * @param CollectionEditor  $editor  Service de modification des collections
     *
     * @return Response Redirection vers la page de la collection
     */
    #[Route('/liste/{id}/edit/visibility', name: 'update_liste_visibility', methods: ['POST'])]
    public function updateVisibility(
        UserAccesChecker $uac, 
        Liste $liste, 
        Request $request, 
        CollectionEditor $editor
        ): Response
    {
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $editor->updateVisibility($liste, $request);
        $this->addFlash('success', 'Visibilité mise à jour avec succès.');

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    /**
     * Met à jour l’image d’une collection.
     *
     * - Accessible uniquement au propriétaire ou aux membres du staff.
     * - L’image est récupérée depuis la requête.
     * - Le fichier est enregistré localement via le service `UploadManager`.
     * - Si le traitement échoue, un message flash d’erreur est affiché.
     *
     * @param UploadManager      $uploadManager Service de gestion des fichiers locaux
     * @param UserAccesChecker   $uac           Vérifie les permissions d'accès
     * @param Request            $request       Requête HTTP contenant le fichier image
     * @param Liste              $liste         Collection à modifier
     * @param CollectionEditor   $editor        Service de modification des collections
     *
     * @return Response Redirection vers la page de la collection
     */
    #[Route('/liste/{id}/update-image', name: 'liste_update_image', methods: ['POST'])]
    public function updateImage(
        UploadManager $uploadManager, 
        UserAccesChecker $uac, 
        Request $request, 
        Liste $liste, 
        CollectionEditor $editor
        ): Response {
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $result = $editor->updateImage($liste, $request, $uploadManager);

        if ($result === true) {
            $this->addFlash('success', 'Image mise à jour avec succès.');
        } elseif ($result === null) {
            $this->addFlash('error', 'Image invalide ou manquante.');
        } else {
            $this->addFlash('error', 'Le fichier n’a pas pu être traité. Format non autorisé.');
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    /**
     * Supprime une collection si l'utilisateur en a les droits.
     *
     * - Vérifie les permissions (propriétaire ou staff)
     * - Vérifie le token CSRF
     * - Supprime la collection ainsi que l’archive ZIP associée (asynchrone)
     * - Affiche un message flash selon le résultat
     *
     * @param UserAccesChecker       $uac      Vérifie les permissions d'accès
     * @param Request                $request  Contient le token CSRF
     * @param Liste                  $liste    Collection à supprimer
     * @param CollectionEditor       $editor   Service de gestion des collections
     *
     * @return Response Redirection vers la liste des collections
     */
    #[Route('/collection/delete/{id}', name: 'delete_collection', methods: ['POST'])]
    public function deleteListe(
        UserAccesChecker $uac, 
        Request $request, 
        Liste $liste, 
        CollectionEditor $editor
        ): Response {
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $formToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_collection_' . $liste->getId(), $formToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_collection');
        }

        try {
            $editor->delete($liste);
            $this->addFlash('success', 'Collection supprimée avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_collection');
    }



    /**
     * Retire un add-on d'une collection.
     *
     * - Vérifie les permissions et le token CSRF
     * - Supprime l’add-on de la collection
     * - Recrée l’archive ZIP avec les add-ons restants (asynchrone)
     * - Affiche un message flash selon le résultat
     *
     * @param UserAccesChecker   $uac      Vérifie les droits d'accès
     * @param Liste              $liste    Collection concernée
     * @param int                $addonId  ID de l'add-on à retirer
     * @param Request            $request  Requête HTTP contenant le token CSRF
     * @param CollectionEditor   $editor   Service métier pour les modifications
     *
     * @return Response Redirection vers la page de la collection
     */
    #[Route('/liste/{id}/remove-addon/{addonId}', name: 'remove_addon_from_liste', methods: ['POST'])]
    public function removeAddonFromListe(
        UserAccesChecker $uac, 
        Liste $liste, 
        int $addonId, 
        Request $request, 
        CollectionEditor $editor
        ): Response {
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('remove_addon_' . $addonId, $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $editor->removeAddon($liste, $addonId);
            $this->addFlash('success', 'Add-on retiré de la collection.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    /**
     * Ajoute un add-on à une collection à partir d'une URL.
     *
     * Vérifie les droits d'accès, le token CSRF et délègue le traitement au service dédié.
     * Affiche un message flash selon le résultat.
     *
     * @param UserAccesChecker $uac       Vérifie les droits d'accès
     * @param Liste            $liste     Collection ciblée
     * @param Request          $request   Contient l'URL et le token CSRF
     * @param CollectionEditor $editor    Service métier pour les opérations de collection
     *
     * @return Response
     */
    #[Route('/liste/{id}/add-addon', name: 'add_addon_to_liste', methods: ['POST'])]
    public function addAddonToListe(
        UserAccesChecker $uac,
        Liste $liste,
        Request $request,
        CollectionEditor $editor
    ): Response {
        if (!($uac->isOwnerOfListe($liste) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add_addon', $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $status = $editor->addAddonFromUrl($liste, $request);

        match ($status) {
            'success' => $this->addFlash('success', 'Add-on ajouté à la collection.'),
            'already_present' => $this->addFlash('warning', 'Cet add-on est déjà présent dans la collection.'),
            'invalid_url' => $this->addFlash('error', 'Veuillez entrer une URL valide.'),
            'invalid_data' => $this->addFlash('error', 'L\'URL ne correspond pas à un add-on valide.'),
            default => $this->addFlash('error', 'Erreur : ' . $status),
        };

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }



    /**
     * Permet de télécharger tous les add-ons d’une collection sous forme d’archive ZIP.
     *
     * - Vérifie la validité du token CSRF pour sécuriser la requête.
     * - Récupère les URLs des add-ons liés à la collection.
     * - Incrémente le compteur de téléchargements de la collection.
     * - Génère une archive ZIP via le service AddonDownloader.
     * - Retourne une réponse HTTP permettant le téléchargement direct du fichier.
     *
     * @param UserAccesChecker       $uac              Vérifie les droits d’accès
     * @param Liste                  $liste            Collection contenant les add-ons
     * @param Request                $request          Requête HTTP contenant le token CSRF
     * @param BlenderAPI             $blenderAPI       (Non utilisé ici, injecté pour compatibilité)
     * @param AddonDownloader        $addonDownloader  Service de création de l’archive ZIP
     * @param EntityManagerInterface $em               Gestionnaire Doctrine
     *
     * @return BinaryFileResponse Fichier ZIP prêt au téléchargement
     */
    #[Route('/liste/{id}/download', name: 'liste_download_addons', methods: ['POST'])]
    public function downloadAddonsFromListe(
        UserAccesChecker $uac,
        Liste $liste,
        Request $request,
        CollectionDownloader $downloader
    ): BinaryFileResponse {
        if (!$this->isCsrfTokenValid('download_addons_' . $liste->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!($uac->isConnected())) {
            throw $this->createAccessDeniedException('Vous n’avez pas les droits pour télécharger cette collection.');

        }

        return $downloader->download($liste);
    }



    /**
     * Ajoute un commentaire à une collection.
     *
     * - Vérifie que l’utilisateur est connecté et vérifié.
     * - Valide le token CSRF.
     * - Crée un commentaire associé à l’utilisateur et à la collection.
     *
     * @param UserAccesChecker $uac       Vérifie les droits d’accès
     * @param Liste            $liste     Collection ciblée
     * @param Request          $request   Requête HTTP contenant le commentaire
     * @param CollectionEditor $editor    Service de gestion des modifications de collection
     *
     * @return Response Redirection vers la page de la collection
     */
    #[Route('/liste/{id}/comment', name: 'liste_comment', methods: ['POST'])]
    public function addComment(
        UserAccesChecker $uac,
        Liste $liste,
        Request $request,
        CollectionEditor $editor
    ): Response {
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        if (!$this->isCsrfTokenValid('add_comment_' . $liste->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $editor->addComment($liste, $request);

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    /**
     * Répond à un commentaire existant (Post).
     *
     * Vérifie que l'utilisateur est connecté et vérifié, puis délègue la création de la réponse au service.
     * Protégé contre les attaques CSRF.
     *
     * @param UserAccesChecker  $uac     Contrôle d'accès
     * @param Post              $post    Commentaire parent
     * @param Request           $request Requête contenant le contenu et le token CSRF
     * @param CollectionEditor  $editor  Service de gestion de la collection
     *
     * @return Response Redirection vers la page de la collection associée
     */
    #[Route('/post/{id}/reply', name: 'post_reply', methods: ['POST'])]
    public function replyToPost(
        UserAccesChecker $uac,
        Post $post,
        Request $request,
        CollectionEditor $editor
    ): Response {
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        if (!$this->isCsrfTokenValid('reply_' . $post->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $editor->replyToPost($post, $request);

        return $this->redirectToRoute('liste_show', [
            'id' => $post->getCommentaire()->getId()
        ]);
    }

    /**
     * Supprime un commentaire (Post) si l'utilisateur est autorisé.
     *
     * Vérifie la propriété du commentaire ou les droits de staff.
     * Protégé par un token CSRF.
     *
     * @param UserAccesChecker  $uac     Contrôle d'accès
     * @param Post              $post    Commentaire à supprimer
     * @param Request           $request Requête contenant le token CSRF
     * @param CollectionEditor  $editor  Service de gestion de la collection
     *
     * @return Response Redirection vers la page de la liste associée
     */
    #[Route('/post/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function deletePost(
        UserAccesChecker $uac,
        Post $post,
        Request $request,
        CollectionEditor $editor
    ): Response {
        if (!($uac->isOwnerOfPost($post) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        if ($this->isCsrfTokenValid('delete_post_' . $post->getId(), $request->request->get('_token'))) {
            $editor->deletePost($post);
        }

        return $this->redirectToRoute('liste_show', [
            'id' => $post->getCommentaire()->getId()
        ]);
    }

    /**
     * Supprime une réponse à un commentaire (SousPost).
     *
     * Vérifie la propriété de la réponse ou les droits de staff.
     * Protégé par un token CSRF.
     *
     * @param UserAccesChecker  $uac       Contrôle d'accès
     * @param SousPost          $sousPost  Réponse à supprimer
     * @param Request           $request   Requête contenant le token CSRF
     * @param CollectionEditor  $editor    Service de gestion de la collection
     *
     * @return Response Redirection vers la page de la collection associée
     */
    #[Route('/souspost/{id}/delete', name: 'souspost_delete', methods: ['POST'])]
    public function deleteSousPost(
        UserAccesChecker $uac,
        SousPost $sousPost,
        Request $request,
        CollectionEditor $editor
    ): Response {
        if (!($uac->isOwnerOfSousPost($sousPost) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        if ($this->isCsrfTokenValid('delete_souspost_' . $sousPost->getId(), $request->request->get('_token'))) {
            $editor->deleteSousPost($sousPost);
        }

        return $this->redirectToRoute('liste_show', [
            'id' => $sousPost->getPost()->getCommentaire()->getId()
        ]);
    }

    /**
     * Like ou retire un like d’un commentaire (Post).
     *
     * Vérifie que l'utilisateur est connecté et vérifié. Si le like existe déjà, il est retiré.
     * Sinon, l'utilisateur est ajouté à la liste des personnes ayant aimé le commentaire.
     *
     * @param UserAccesChecker  $uac     Contrôle d'accès
     * @param Post              $post    Commentaire ciblé
     * @param CollectionEditor  $editor  Service de gestion de collection
     *
     * @return Response Redirection vers la page de la collection avec ancre sur le commentaire
     */
    #[Route('/post/{id}/like', name: 'post_like', methods: ['POST'])]
    public function likePost(
        UserAccesChecker $uac,
        Post $post,
        CollectionEditor $editor
    ): Response {
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        $editor->toggleLikeOnPost($post);

        return $this->redirectToRoute('liste_show', [
            'id' => $post->getCommentaire()->getId()
        ]);
    }

    /**
     * Like ou retire un like d’une réponse (SousPost).
     *
     * Vérifie que l'utilisateur est connecté et vérifié. Bascule l'état du like sur le sous-commentaire.
     *
     * @param UserAccesChecker  $uac       Contrôle d'accès
     * @param SousPost          $sousPost  Réponse ciblée
     * @param CollectionEditor  $editor    Service de gestion de collection
     *
     * @return Response Redirection vers la page de la collection avec ancre sur le commentaire parent
     */
    #[Route('/souspost/{id}/like', name: 'souspost_like', methods: ['POST'])]
    public function likeSousPost(
        UserAccesChecker $uac,
        SousPost $sousPost,
        CollectionEditor $editor
    ): Response {
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        $editor->toggleLikeOnSousPost($sousPost);

        return $this->redirectToRoute('liste_show', [
            'id' => $sousPost->getPost()->getCommentaire()->getId()
        ]);
    }

    /**
     * Ajoute ou retire une collection (liste) des favoris de l'utilisateur connecté.
     *
     * Vérifie que l'utilisateur est connecté et vérifié. Bascule l'état de favori de la liste.
     * Redirige vers la page de la collection concernée.
     *
     * @param UserAccesChecker  $uac      Contrôle d'accès
     * @param Liste             $liste    Collection ciblée
     * @param CollectionEditor  $editor   Service métier pour gérer les collections
     *
     * @return RedirectResponse Redirection vers la page de la liste
     */
    #[Route('/liste/{id}/toggle-favoris', name: 'toggle_favoris')]
    public function toggleFavoris(
        UserAccesChecker $uac,
        Liste $liste,
        CollectionEditor $editor
    ): RedirectResponse {
        if (!($uac->isAllowed() && $uac->isVerified())) {
            return $uac->redirectingGlobal();
        }

        $editor->toggleFavoris($liste);

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }


}
