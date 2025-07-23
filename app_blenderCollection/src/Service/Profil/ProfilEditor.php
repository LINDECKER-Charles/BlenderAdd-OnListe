<?php

namespace App\Service\Profil;

use App\Entity\User;
use App\Service\AdminLogger;
use App\Service\UploadManager;
use App\Security\EmailVerifier;
use App\Repository\UserRepository;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProfilEditor
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private AdminLogger $logger,
        private Security $security,
        private UserRepository $userRepository,
        private EmailVerifier $emailVerifier,
        private UploadManager $uploadManager
    ) {}

    /**
     * Supprime un utilisateur de la base de données, le déconnecte et invalide sa session.
     *
     * À utiliser pour les suppressions initiées par l'utilisateur lui-même.
     *
     * @param User $user L'utilisateur à supprimer et à déconnecter.
     */
    public function deleteUserAndLogout(User $user): void
    {
        $this->logger->log(
        'Suppression et déconnexion utilisateur',
        $this->security->getUser(),
        $user->getName() . ' #' . $user->getId(),
        'Suppression effectuée avec déconnexion immédiate.');

        $this->em->remove($user);
        $this->em->flush();

        $this->tokenStorage->setToken(null);
        $session = $this->requestStack->getSession();
        $session->invalidate();
    }

    /**
     * Supprime définitivement un utilisateur de la base de données.
     *
     * À utiliser lorsque la suppression de compte ne nécessite pas de déconnexion immédiate.
     *
     * @param User $user L'utilisateur à supprimer.
     */
    public function deleteUser(User $user): void
    {
        $this->logger->log(
        'Suppression utilisateur',
        $this->security->getUser(),
        $user->getName() . ' #' . $user->getId(),
        'Suppression effectué');
        
        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Met à jour le nom d’un utilisateur, après validation de son unicité.
     *
     * - Vérifie si le nouveau nom est déjà utilisé dans la base.
     * - Si oui, ajoute un message flash d'erreur et retourne `false`.
     * - Si non, met à jour le nom, enregistre les modifications, et retourne `true`.
     * - En cas d’erreur ou de nom vide, ajoute un message flash d'erreur et retourne `false`.
     *
     * @param User   $user     L'utilisateur dont le nom doit être mis à jour.
     * @param string $newName  Le nouveau nom souhaité.
     *
     * @return bool  `true` si la mise à jour a réussi, `false` sinon.
     */
    public function updateName(User $user, string $newName): bool
    {
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->clear();

        if ($this->userRepository->findOneBy(['name' => $newName])) {
            $session->getFlashBag()->add('error', 'Name is taken!');
            return false;
        }

        if ($newName) {
            $this->logger->log(
                'Changement de nom utilisateur',
                $this->security->getUser(),
                $user->getName() . ' #' . $user->getId(),
                "Nouveau nom : \"$newName\""
            );
            $user->setName($newName);
            $this->em->flush();
            $session->getFlashBag()->add('success', 'Name updated!');
            return true;
        }

        $session->getFlashBag()->add('error', 'Error: cannot update name!');
        return false;
    }

    /**
     * Met à jour l'email d’un utilisateur, invalide son ancien statut de vérification
     * et déclenche l'envoi d'un nouveau mail de confirmation.
     *
     * @param User $user       Utilisateur concerné.
     * @param string $newEmail Nouveau mail proposé.
     * @return bool            True si succès, false sinon.
     */
    public function updateEmail(User $user, string $newEmail): bool
    {
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->clear();

        if (!$newEmail) {
            $session->getFlashBag()->add('error', 'Error: email is empty.');
            return false;
        }

        $this->logger->log(
            'Changement d’email utilisateur',
            $this->security->getUser(),
            $user->getName() . ' #' . $user->getId(),
            "Nouvel email : \"$newEmail\" (vérification requise)"
        );

        $user->setEmail($newEmail);
        $user->setIsVerified(false);

        $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
            (new TemplatedEmail())
                ->from(new Address('no-reply@blender-collection.com', 'Blender Collection Mail Bot'))
                ->to($newEmail)
                ->subject('Please Confirm your Email')
                ->htmlTemplate('registration/confirmation_email.html.twig')
        );

        $this->em->flush();

        $session->getFlashBag()->add('success', 'Email updated!');
        $session->getFlashBag()->add('warning', 'You need to verify your new email!');
        return true;
    }

    /**
     * Met à jour la description d’un utilisateur.
     *
     * Enregistre la nouvelle description si elle est valide et log l’action.
     * Affiche un message flash selon le succès ou l’échec.
     *
     * @param User $user               L’utilisateur dont la description doit être mise à jour.
     * @param string|null $description La nouvelle description (peut être vide ou nulle).
     *
     * @return bool                    Vrai si la description a été mise à jour avec succès.
     */
    public function updateDescription(User $user, ?string $description): bool
    {
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->clear();

        if ($description !== null) {
            $user->setDescription($description);
            $this->em->flush();

            $this->logger->log(
                'Mise à jour de la description',
                $this->security->getUser(),
                $user->getName() . ' #' . $user->getId(),
                'Nouvelle description enregistrée.'
            );

            $session->getFlashBag()->add('success', 'Description updated successfully!');
            return true;
        }

        $session->getFlashBag()->add('error', 'Error: Could not update description.');
        return false;
    }

    /**
     * Met à jour l’avatar d’un utilisateur.
     *
     * Supprime les anciennes images associées à l’utilisateur, utilise UploadManager
     * pour enregistrer la nouvelle image, puis met à jour l'entité utilisateur.
     * Un message flash est ajouté selon le résultat.
     *
     * @param User $user                L’utilisateur dont l’image est modifiée.
     * @param UploadedFile|null $file  Le fichier téléchargé depuis la requête.
     * @param string $destination      Le dossier de destination.
     * @param UploadManager $uploadManager Service de gestion des fichiers.
     *
     * @return bool                    Vrai si l’image a bien été mise à jour.
     */
    public function updateAvatar(User $user, ?UploadedFile $file, string $destination): bool
    {
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->clear();

        if (!$file || !$file->isValid()) {
            $session->getFlashBag()->add('error', 'Image invalide ou absente.');
            return false;
        }

        // Supprimer anciens fichiers
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $old = $destination . '/' . $user->getId() . '_avatar.' . $ext;
            if (file_exists($old)) {
                unlink($old);
            }
        }

        $extension = $file->guessExtension() ?: 'png';
        $filename = $user->getId() . '_avatar.' . $extension;

        $savedName = $this->uploadManager->uploadLocalFile($file, $filename, $destination);

        if ($savedName) {
            $user->setPathImg('/uploads/avatar/' . $savedName);
            $this->em->flush();

            $this->logger->log(
                'Mise à jour de l’avatar',
                $this->security->getUser(),
                $user->getName() . ' #' . $user->getId(),
                'Nouvelle image enregistrée.'
            );

            $session->getFlashBag()->add('success', 'Image mise à jour avec succès !');
            return true;
        }

        $session->getFlashBag()->add('error', 'Erreur lors de l’envoi de l’image.');
        return false;
    }


}
