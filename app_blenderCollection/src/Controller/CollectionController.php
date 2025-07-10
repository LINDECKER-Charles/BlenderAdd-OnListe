<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Addon;
use App\Entity\Liste;
use App\Entity\SousPost;
use App\Service\BlenderAPI;
use App\Service\AddonsManager;
use App\Service\AddonsScraper;
use App\Service\AddonDownloader;
use App\Repository\ListeRepository;
use App\Service\UploadManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    /**
     * Affiche le formulaire de création d'une collection et le traite lors de la soumission POST.
     *
     * Étapes :
     * - Vérifie si l'utilisateur est connecté et vérifié
     * - Récupère les champs du formulaire (nom, description, visibilité)
     * - Upload l’image (locale ou récupérée depuis le 1er add-on)
     * - Crée la collection et y associe les add-ons de la session
     * - Enregistre la collection et nettoie la session
     *
     * @param Request $request Requête HTTP (POST pour la création)
     * @param SluggerInterface $slugger Utilisé pour normaliser les noms de fichiers uploadés
     * @param EntityManagerInterface $em Pour interagir avec la base de données
     * @param UploadManager $uploadManager Service pour l’upload d’image
     *
     * @return Response Redirige vers la page de la collection ou affiche le formulaire
     */
    #[Route('/collection/add', name: 'create_collection')]
    public function addCollection(Request $request, SluggerInterface $slugger, EntityManagerInterface $em, UploadManager $uploadManager): Response
    {
        //On verifie que l'utilisateur est loger et verifier
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        } elseif (!$user->isVerified()) {
            return $this->render('registration/should_verify.html.twig');
        } elseif (in_array('ROLE_LOCK', $user->getRoles(), true)) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Votre compte est verrouillé. Veuillez contacter un administrateur.');
            return $this->redirectToRoute('app_home');
        } elseif (in_array('ROLE_BAN', $user->getRoles(), true)) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Votre compte a été banni.');
            return $this->redirectToRoute('app_home');
        }


        if ($request->isMethod('POST')) {
            $fullName = $request->request->get('fullName');
            $description = $request->request->get('description');
            $isVisible = $request->request->getBoolean('isVisible');

            //Creation de la liste
            $liste = new Liste();
            $liste->setName($fullName);
            $liste->setDescription($description);

            // Recuperer les add-ons valides dans la session
            $sessionAddons = $request->getSession()->get('valid_addons', []);

            /** @var UploadedFile|null $imageFile */
            $imageFile = $request->files->get('image');
            $imageFilename = null;
            if ($imageFile) {
                $imageFilename = $uploadManager->uploadLocalFile($imageFile);
            } elseif (!empty($sessionAddons) && isset($sessionAddons[0][1]['image'])) {
                $imageFilename = $uploadManager->uploadFromUrl($sessionAddons[0][1]['image']);
            }

            if ($imageFilename) {
                $liste->setImage($imageFilename);
            }
            
            $liste->setUsser($user);
            $liste->setIsVisible($isVisible);
            $liste->setDateCreation(new \DateTime());
            $liste->setDownload(0);




            foreach ($sessionAddons as [$url, $data]) {
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
            $em->flush();

            // Nettoyage session
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
    public function addAddon(Request $request, AddonsScraper $scraper, AddonsManager $am, SessionInterface $session): JsonResponse {

        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        } elseif (!$user->isVerified()) {
            return $this->json(['error' => 'Utilisateur non vérifié'], 403);
        } elseif (in_array('LOCK', $user->getRoles(), true)) {
            return $this->json(['error' => 'Accès refusé : votre compte est temporairement verrouillé.'], 403);
        } elseif (in_array('BAN', $user->getRoles(), true)) {
            return $this->json(['error' => 'Accès interdit : votre compte a été banni.'], 403);
        }

        $url = $request->request->get('url');

        if (!$url) {
            return $this->json(['error' => 'Aucune URL fournie'], 400);
        } else if (!$am->isValidAddonUrl($url)) {
            return $this->json(['error' => 'URL non valide'], 400);
        }

        try {
            $data = $scraper->getAddOn($url);
            $am->addAddOn($url, $data, $session);
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
    public function removeAddon(Request $request, AddonsManager $am, SessionInterface $session): JsonResponse {
        
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        } elseif (!$user->isVerified()) {
            return $this->json(['error' => 'Utilisateur non vérifié'], 403);
        } elseif (in_array('LOCK', $user->getRoles(), true)) {
            return $this->json(['error' => 'Accès refusé : votre compte est temporairement verrouillé.'], 403);
        } elseif (in_array('BAN', $user->getRoles(), true)) {
            return $this->json(['error' => 'Accès interdit : votre compte a été banni.'], 403);
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
    public function getSessionAddons(SessionInterface $session): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        } elseif (!$user->isVerified()) {
            return $this->json(['error' => 'Utilisateur non vérifié'], 403);
        } elseif (in_array('LOCK', $user->getRoles(), true)) {
            return $this->json(['error' => 'Accès refusé : votre compte est temporairement verrouillé.'], 403);
        } elseif (in_array('BAN', $user->getRoles(), true)) {
            return $this->json(['error' => 'Accès interdit : votre compte a été banni.'], 403);
        }
        
        $addons = $session->get('valid_addons', []);
        if (empty($addons)) {
            return $this->json(['empty' => true]);
        }

        return $this->json(array_values($addons));
    }


    /* detail.html.twig */
/* PAS FINI ICI */
     /* Supprimer une collection */
    #[Route('/collection/delete/{id}', name: 'delete_collection', methods: ['POST'])]
    public function deleteListe(Request $request, Liste $liste, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        } elseif (!$user->isVerified()) {
            return $this->render('registration/should_verify.html.twig');
        } elseif (in_array('LOCK', $user->getRoles(), true)) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Votre compte est verrouillé. Veuillez contacter un administrateur.');
            return $this->redirectToRoute('app_home');
        } elseif (in_array('BAN', $user->getRoles(), true)) {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Votre compte a été banni.');
            return $this->redirectToRoute('app_home');
        }

        $formToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_collection_' . $liste->getId(), $formToken)) {
            try {
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
        /* Affichage de la liste */
    #[Route('/liste/{id}', name: 'liste_show')]
    public function show(int $id, ListeRepository $listeRepository): Response
    {
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
    #[Route('/liste/{id}/update-image', name: 'liste_update_image', methods: ['POST'])]
    public function updateImage(Request $request, Liste $liste, EntityManagerInterface $em, SluggerInterface $slugger): Response {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY') || $this->getUser() !== $liste->getUsser()) {
            throw $this->createAccessDeniedException();
        }

        $image = $request->files->get('image');

        if ($image && $image->isValid()) {
            $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$image->guessExtension();

            $image->move($this->getParameter('uploads_directory'), $newFilename);

            $liste->setImage($newFilename);
            $em->flush();
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

        /* Ajouter et retirer addon dans une collection */
    #[Route('/liste/{id}/remove-addon/{addonId}', name: 'remove_addon_from_liste', methods: ['POST'])]
    public function removeAddonFromListe(Liste $liste, int $addonId, EntityManagerInterface $em, Request $request): Response {
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
    public function addAddonToListe(Liste $liste, Request $request, EntityManagerInterface $em, AddonsScraper $scraper): Response {
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

        /* Téléchargement de addon */
    #[Route('/liste/{id}/download', name: 'liste_download_addons', methods: ['POST'])]
    public function downloadAddonsFromListe(Liste $liste, Request $request, BlenderAPI $blenderAPI, AddonDownloader $addonDownloader, EntityManagerInterface $em): BinaryFileResponse {

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
        if(!$liste->getDownload()){
            $liste->setDownload(1);
        }else{
            $liste->setDownload($liste->getDownload() + 1);
        }
        $em->persist($liste);
        $em->flush();
        return $addonDownloader->downloadAndZip($urls, 'collection_' . $liste->getName() . '.zip');
    }

        /* Ajouter un commentaire */
    #[Route('/liste/{id}/comment', name: 'liste_comment', methods: ['POST'])]
    public function addComment(Liste $liste, Request $request, EntityManagerInterface $em, Security $security): Response {

        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
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
        /* Répondre à un commentaire */
    #[Route('/post/{id}/reply', name: 'post_reply', methods: ['POST'])]
    public function replyToPost(Post $post, Request $request, EntityManagerInterface $em): Response {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
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
        /* Supression Commentaire */
    #[Route('/post/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function deletePost(Post $post, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user || $post->getCommenter() !== $user) {
            throw $this->createAccessDeniedException('Tu ne peux pas supprimer ce commentaire.');
        }

        if ($this->isCsrfTokenValid('delete_post_' . $post->getId(), $request->request->get('_token'))) {
            $em->remove($post);
            $em->flush();
        }

        return $this->redirectToRoute('liste_show', ['id' => $post->getCommentaire()->getId()]);
    }

        /* Supression d'une réponse */
    #[Route('/souspost/{id}/delete', name: 'souspost_delete', methods: ['POST'])]
    public function deleteSousPost(SousPost $sousPost, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user || $sousPost->getCommenter() !== $user) {
            throw $this->createAccessDeniedException('Tu ne peux pas supprimer cette réponse.');
        }

        if ($this->isCsrfTokenValid('delete_souspost_' . $sousPost->getId(), $request->request->get('_token'))) {
            $em->remove($sousPost);
            $em->flush();
        }

        return $this->redirectToRoute('liste_show', ['id' => $sousPost->getPost()->getCommentaire()->getId()]);
    }



        /* Liker un commentaire */
    #[Route('/post/{id}/like', name: 'post_like', methods: ['POST'])]
    public function likePost(Post $post, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($post->getLiker()->contains($user)) {
            $post->removeLiker($user);
        } else {
            $post->addLiker($user);
        }

        $em->flush();

        return $this->redirect($this->generateUrl('liste_show', ['id' => $post->getCommentaire()->getId()]) . '#post-' . $post->getId());
    }
        /* Liker un sous commentaire */
    #[Route('/souspost/{id}/like', name: 'souspost_like', methods: ['POST'])]
    public function likeSousPost(SousPost $sousPost, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($sousPost->getLikes()->contains($user)) {
            $sousPost->removeLike($user);
        } else {
            $sousPost->addLike($user);
        }

        $em->flush();

        return $this->redirect($this->generateUrl('liste_show', ['id' => $sousPost->getPost()->getCommentaire()->getId()]) . '#post-' . $sousPost->getPost()->getId());
    }

        /* Ajouter une collection en favoris */
    #[Route('/liste/{id}/toggle-favoris', name: 'toggle_favoris')]
    public function toggleFavoris(Liste $liste, EntityManagerInterface $em, Security $security): RedirectResponse
    {
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
