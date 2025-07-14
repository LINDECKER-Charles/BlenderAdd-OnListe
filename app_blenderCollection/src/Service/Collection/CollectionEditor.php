<?php 
namespace App\Service\Collection;

use App\Entity\Post;
use App\Entity\Addon;
use App\Entity\Liste;
use App\Entity\SousPost;
use App\Service\AdminLogger;
use App\Service\AddonsScraper;
use App\Service\UploadManager;
use App\Service\AddonDownloader;
use App\Message\DeleteZipMessage;
use App\Message\DownloadZipMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CollectionEditor
{
    public function __construct(
        private readonly CollectionValidator $validator,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly AddonDownloader $downloader,
        private readonly AddonsScraper $scraper,
        private readonly Security $security,
        private AdminLogger $logger,
    ) {}

    /**
     * Met à jour le nom d'une collection.
     * Supprime l'ancienne archive ZIP et en recrée une nouvelle avec le nouveau nom.
     *
     * @param Liste   $liste   Collection à modifier
     * @param Request $request Requête contenant le nouveau nom
     */
    public function updateName(Liste $liste, Request $request): void
    {

        $rawName = $request->request->get('name');
        $name = strip_tags($rawName);

        $this->validator->validateNameIsUnique($name);

        $oldZip = 'collection_' . $this->slugify($liste->getName()) . '.zip';
        $this->bus->dispatch(new DeleteZipMessage($oldZip));

        $user = $this->security->getUser();
        $this->logger->log(
        'Update Name Collection',
        $user,
        $liste->getName() . ' #' . $liste->getId(),
        'Changement du nom de la liste de ' . $liste->getName() . ' en ' . $name);

        $liste->setName($name);
        $this->em->flush();

        $urls = $this->downloader->resolveArchiveUrls(
            array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray())
        );

        $newZip = 'collection_' . $this->slugify($name) . '.zip';
        $this->bus->dispatch(new DownloadZipMessage($urls, $newZip));
    }

    /**
     * Met à jour la description d'une collection.
     *
     * @param Liste   $liste   Collection à modifier
     * @param Request $request Requête contenant la nouvelle description
     */
    public function updateDescription(Liste $liste, Request $request): void
    {
        $rawDescription = $request->request->get('description');
        $description = strip_tags($rawDescription);

        $user = $this->security->getUser();
        $this->logger->log(
            'Update Description Collection',
            $user,
            $liste->getName() . ' #' . $liste->getId(),
            'Mise à jour de la description de la collection ' . $liste->getDescription() . ' en ' . $description
        );

        $liste->setDescription($description);
        $this->em->flush();
        

    }

    /**
     * Met à jour la visibilité d'une collection (publique ou privée).
     *
     * @param Liste   $liste   Collection à modifier
     * @param Request $request Requête contenant le champ "isVisible"
     */
    public function updateVisibility(Liste $liste, Request $request): void
    {

        $isVisible = filter_var($request->request->get('isVisible'), FILTER_VALIDATE_BOOLEAN);

        $oldVisibility = $liste->isVisible() ? 'visible' : 'invisible';
        $newVisibility = $isVisible ? 'visible' : 'invisible';
        $user = $this->security->getUser();
        $this->logger->log(
            'Update Visibility Collection',
            $user,
            $liste->getName() . ' #' . $liste->getId(),
            'Visibilité changée de ' . $oldVisibility . ' en ' . $newVisibility
        );

        $liste->setIsVisible($isVisible);
        $this->em->flush();
    }

    /**
     * Met à jour l’image d'une collection si une image valide est envoyée.
     *
     * @param Liste         $liste         Collection concernée
     * @param Request       $request       Requête contenant le fichier
     * @param UploadManager $uploadManager Service de gestion des uploads
     *
     * @return bool|string|null True si succès, false si échec MIME, null si fichier absent ou invalide
     */
    public function updateImage(Liste $liste, Request $request, UploadManager $uploadManager): bool|string|null
    {
        /** @var UploadedFile|null $image */
        $image = $request->files->get('image');

        if (!$image || !$image->isValid()) {
            return null; // image absente ou invalide
        }

        $filename = $uploadManager->uploadLocalFile($image);

        if ($filename === null) {
            return false; // échec upload (MIME non autorisé)
        }

        $user = $this->security->getUser();
        $this->logger->log(
            'Update Image Collection',
            $user,
            $liste->getName() . ' #' . $liste->getId(),
            'Image mise à jour de  ' . $liste->getImage() . ' en ' . $filename
        );

        $liste->setImage($filename);
        $this->em->flush();

        return true;
    }

    /**
     * Supprime une collection et l’archive ZIP associée.
     *
     * @param Liste $liste Collection à supprimer
     */
    public function delete(Liste $liste): void
    {
        $zipName = 'collection_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $liste->getName()) . '.zip';
        $this->bus->dispatch(new DeleteZipMessage($zipName));

        $user = $this->security->getUser();
        $this->logger->log(
            'Delete Collection',
            $user,
            $liste->getName() . ' #' . $liste->getId(),
            'Suppression de la collection' . $liste->getName()
        );

        $this->em->remove($liste);
        $this->em->flush();
    }

    /**
     * Supprime un add-on d'une collection et met à jour l’archive ZIP.
     *
     * @param Liste $liste   Collection concernée
     * @param int   $addonId ID de l’add-on à retirer
     *
     * @throws \InvalidArgumentException Si l'add-on est introuvable
     */
    public function removeAddon(Liste $liste, int $addonId): void
    {
        $addon = $this->em->getRepository(Addon::class)->find($addonId);
        if (!$addon) {
            throw new \InvalidArgumentException('Add-on introuvable.');
        }

        $user = $this->security->getUser();
        $this->logger->log(
            'Remove Addon Collection',
            $user,
            $liste->getName() . ' #' . $liste->getId(),
            'Retrait de l’add-on ' . $addon->getIdBlender()
        );

        $liste->removeAddon($addon);
        $this->em->flush();

        $urls = $this->downloader->resolveArchiveUrls(
            array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray())
        );
        $zipName = 'collection_' . $this->slugify($liste->getName()) . '.zip';

        $this->bus->dispatch(new DownloadZipMessage($urls, $zipName));
    }

    /**
     * Ajoute un add-on à la collection à partir d'une URL (scraping).
     * Crée l’add-on s’il n’existe pas encore.
     * Met à jour l’archive ZIP après ajout.
     *
     * @param Liste   $liste   Collection ciblée
     * @param Request $request Requête contenant le champ "idBlender"
     *
     * @return string Statut de l’opération : 'success', 'already_present', 'invalid_url', 'invalid_data', ou 'error: ...'
     */
    public function addAddonFromUrl(Liste $liste, Request $request): string
    {
        $url = trim($request->request->get('idBlender'));

        if (empty($url)) {
            return 'invalid_url';
        }

        try {
            $data = $this->scraper->getAddOn($url);

            if (
                empty($data['title']) ||
                empty($data['size']) ||
                empty($data['image'])
            ) {
                return 'invalid_data';
            }

            $addon = $this->em->getRepository(Addon::class)->findOneBy(['idBlender' => $url]);

            if (!$addon) {
                $addon = new Addon();
                $addon->setIdBlender($url);
                $this->em->persist($addon);
            }

            if (!$liste->getAddons()->contains($addon)) {
                $liste->addAddon($addon);
                $this->em->flush();

                // Mise à jour de l’archive ZIP
                $urls = $this->downloader->resolveArchiveUrls(
                    array_map(fn($a) => $a->getIdBlender(), $liste->getAddons()->toArray())
                );
                $zipName = 'collection_' . $this->slugify($liste->getName()) . '.zip';
                $this->bus->dispatch(new DownloadZipMessage($urls, $zipName));

                $user = $this->security->getUser();
                $this->logger->log(
                    'Add Addon Collection',
                    $user,
                    $liste->getName() . ' #' . $liste->getId(),
                    'Ajout de l’add-on ' . $addon->getIdBlender()
                );

                return 'success';
            }

            return 'already_present';

        } catch (\Throwable $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Ajoute un commentaire (Post) à une collection.
     *
     * @param Liste   $liste   Collection commentée
     * @param Request $request Requête contenant le contenu du commentaire
     */
    public function addComment(Liste $liste, Request $request): void
    {
        $content = trim($request->request->get('content'));

        if (!$content) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user) {
            return;
        }

        $comment = new Post();
        $comment->setContent($content);
        $comment->setDateCreation(new \DateTime());
        $comment->setCommentaire($liste);
        $comment->setCommenter($user);

        $this->logger->log(
            'Add Comment Collection',
            $user,
            $liste->getName() . ' #' . $liste->getId(),
            'Ajout d’un commentaire'
        );

        $this->em->persist($comment);
        $this->em->flush();
    }

    /**
     * Répond à un commentaire existant (Post) par un SousPost.
     *
     * @param Post    $post    Commentaire parent
     * @param Request $request Requête contenant la réponse
     */
    public function replyToPost(Post $post, Request $request): void
    {
        $content = trim($request->request->get('content'));
        
        if (!$content) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user) {
            return;
        }

        $reply = new SousPost();
        $reply->setContent($content);
        $reply->setDateCreation(new \DateTime());
        $reply->setPost($post);
        $reply->setCommenter($user);

        $this->em->persist($reply);
        $this->em->flush();

        $this->logger->log(
        'Reply to Comment',
        $user,
        $post->getCommentaire()->getName() . ' #' . $post->getCommentaire()->getId(),
        'Réponse ajoutée à un commentaire (Post #' . $post->getId() . ')'
        );
    }

    /**
     * Supprime un commentaire (Post).
     *
     * @param Post $post Commentaire à supprimer
     */
    public function deletePost(Post $post): void
    {
        $user = $this->security->getUser();
        $this->logger->log(
            'Delete Comment',
            $user,
            $post->getCommentaire()->getName() . ' #' . $post->getCommentaire()->getId(),
            'Suppression du commentaire (Post #' . $post->getId() . ')'
        );

        $this->em->remove($post);
        $this->em->flush();
    }

    /**
     * Supprime une réponse à un commentaire (SousPost).
     *
     * @param SousPost $sousPost Réponse à supprimer
     */
    public function deleteSousPost(SousPost $sousPost): void
    {
        $user = $this->security->getUser();
        $this->logger->log(
            'Delete Reply',
            $user,
            $sousPost->getPost()->getCommentaire()->getName() . ' #' . $sousPost->getPost()->getCommentaire()->getId(),
            'Suppression de la réponse (SousPost #' . $sousPost->getId() . ')'
        );

        $this->em->remove($sousPost);
        $this->em->flush();
    }

    /**
     * Bascule l’état de like d’un commentaire (Post) pour l’utilisateur connecté.
     *
     * @param Post $post Commentaire à liker / disliker
     */
    public function toggleLikeOnPost(Post $post): void
    {
        $user = $this->security->getUser();
        $action = $post->getLiker()->contains($user) ? 'Retrait Like' : 'Ajout Like';


        if ($post->getLiker()->contains($user)) {
            $post->removeLiker($user);
        } else {
            $post->addLiker($user);
        }

        $this->em->flush();
        $this->logger->log(
            $action . ' on Post',
            $user,
            $post->getCommentaire()->getName() . ' #' . $post->getCommentaire()->getId(),
            $action . ' sur le commentaire (Post #' . $post->getId() . ')'
        );
    }

    /**
     * Bascule l’état de like d’une réponse (SousPost) pour l’utilisateur connecté.
     *
     * @param SousPost $sousPost Réponse à liker / disliker
     */
    public function toggleLikeOnSousPost(SousPost $sousPost): void
    {
        $user = $this->security->getUser();
        $action = $sousPost->getLikes()->contains($user) ? 'Retrait Like' : 'Ajout Like';


        if ($sousPost->getLikes()->contains($user)) {
            $sousPost->removeLike($user);
        } else {
            $sousPost->addLike($user);
        }

        $this->logger->log(
        $action . ' on Reply',
        $user,
        $sousPost->getPost()->getCommentaire()->getName() . ' #' . $sousPost->getPost()->getCommentaire()->getId(),
        $action . ' sur la réponse (SousPost #' . $sousPost->getId() . ')'
        );
        $this->em->flush();
    }

    /**
     * Ajoute ou retire une collection des favoris de l’utilisateur connecté.
     *
     * @param Liste $liste Collection ciblée
     */
    public function toggleFavoris(Liste $liste): void
    {
        $user = $this->security->getUser();
        $action = $user->getFavoris()->contains($liste) ? 'Retrait Favoris' : 'Ajout Favoris';

        if (!$user) {
            return;
        }

        if ($user->getFavoris()->contains($liste)) {
            $user->removeFavori($liste);
        } else {
            $user->addFavori($liste);
        }

        $this->em->flush();
        $this->logger->log(
            $action . ' on Collection',
            $user,
            $liste->getName() . ' #' . $liste->getId(),
            $action . ' de la collection aux favoris'
        );
    }



    private function slugify(string $text): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $text);
    }
}

