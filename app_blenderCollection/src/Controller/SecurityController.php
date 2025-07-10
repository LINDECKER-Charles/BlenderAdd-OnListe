<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UploadManager;
use App\Security\EmailVerifier;
use App\Service\MarkdownService;
use App\Service\UserAccesChecker;
use App\Repository\UserRepository;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class SecurityController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    /**
     * Affiche la page de connexion utilisateur.
     *
     * Si l’utilisateur est déjà connecté, il est redirigé vers la page d’accueil.
     * Sinon, récupère l’erreur d’authentification éventuelle et le dernier identifiant saisi.
     *
     * @param UserAccesChecker $uac                Service de vérification d’accès utilisateur.
     * @param AuthenticationUtils $authenticationUtils Utilitaire pour récupérer les infos d'authentification.
     * @param Request $request                     Requête HTTP entrante.
     *
     * @return Response                            Page de connexion ou redirection.
     */

    #[Route(path: '/login', name: 'app_login')]
    public function login(UserAccesChecker $uac, AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($uac->isConnected()) {
            return $this->redirectToRoute('app_home');
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Déconnexion utilisateur.
     *
     * Cette méthode ne sera jamais exécutée : elle est interceptée automatiquement
     * par le firewall `logout` défini dans le système de sécurité de Symfony.
     *
     * @return void
     */
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    /**
     * Affiche le profil de l’utilisateur connecté.
     *
     * Convertit la description (en Markdown) en HTML à l’aide du MarkdownService.
     *
     * @param UserAccesChecker $uac    Vérifie que l'utilisateur est connecté.
     * @param MarkdownService $md      Service de conversion Markdown -> HTML.
     *
     * @return Response                Page de profil utilisateur.
     */
    #[Route('/profil', name: 'app_profil')]
    public function profil(UserAccesChecker $uac, MarkdownService $md): Response
    {
/*         if (!$uac->isConnected()) {
            return $this->redirectToRoute('app_login');
        } */
        $user = $this->getUser();
        $htmlDescription = $md->toHtml($user->getDescription() ?? '');

        return $this->render('security/profil.html.twig', [
            'user' => $user,
            'descriptionHtml' => $htmlDescription,
        ]);
    }
    /**
     * Affiche le profil public d’un utilisateur donné.
     *
     * Permet la consultation du profil d’un autre utilisateur via son ID.
     * La description est convertie de Markdown vers HTML.
     *
     * @param MarkdownService $md   Service de conversion Markdown -> HTML.
     * @param User $user            Utilisateur cible à afficher.
     *
     * @return Response             Page de profil en lecture seule.
     */
    #[Route('/profil/{id}', name: 'app_profil_visiteur')]
    public function profilVisiteur(MarkdownService $md, User $user): Response
    {
        return $this->render('security/profil.html.twig', [
            'user' => $user,
            'descriptionHtml' => $md->toHtml($user->getDescription() ?? ''),
        ]);
    }
    /**
     * Met à jour le nom d’un utilisateur (via formulaire).
     *
     * Vérifie les droits (soit l’utilisateur lui-même, soit un staff).
     * Empêche l’usage d’un nom déjà existant, puis met à jour la base.
     * Affiche des messages flash selon le résultat.
     *
     * @param UserAccesChecker $uac     Vérifie les autorisations.
     * @param User $user                Utilisateur à modifier.
     * @param Request $request          Requête contenant le nouveau nom.
     * @param EntityManagerInterface $em Gestionnaire d'entités Doctrine.
     * @param UserRepository $userRepository Pour vérifier l’unicité du nom.
     *
     * @return Response                 Redirection vers la page de profil.
     */
    #[Route('/updateName/{id}', name: 'app_update_name', methods: ['POST'])]
    public function updateName(UserAccesChecker $uac, User $user, Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        if (!($uac->isConnected($user) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $newName = trim($request->request->get('name'));
        if($userRepository->findOneBy(['name' => $newName])){

            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Name is taken!');
            return $this->redirectToRoute('app_profil');
        }
        if ($newName && $user) {
            $user->setName($newName);
            $em->flush();

            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('success', 'Name updated !');
        }else{
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Error cannot updated name !');
        }

        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }
    /**
     * Met à jour l’adresse e-mail de l’utilisateur et déclenche un e-mail de confirmation.
     *
     * Remet le compte en non-vérifié et envoie un lien de validation à la nouvelle adresse.
     * Affiche des messages flash selon la réussite de l’opération.
     *
     * @param UserAccesChecker $uac         Vérifie les autorisations.
     * @param User $user                    Utilisateur concerné.
     * @param Request $request              Requête contenant le nouvel e-mail.
     * @param EntityManagerInterface $em   Gestionnaire d'entités Doctrine.
     *
     * @return Response                     Redirection vers la page de profil.
     */
    #[Route('/updateEmail/{id}', name: 'app_update_email', methods: ['POST'])]
    public function updateEmail(UserAccesChecker $uac, User $user ,Request $request, EntityManagerInterface $em): Response
    {
        if (!($uac->isConnected($user) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $newEmail = trim($request->request->get('name'));
        if ($newEmail && $user) {
            $user->setEmail($newEmail);
            $user->setIsVerified(false);
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('no-reply@blender-collection.com', 'Blender Collection Mail Bot'))
                    ->to((string) $user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );
            $em->flush();

            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('success', 'Email updated !');
            $this->addFlash('warning', 'You need to verify your new email !');
        }else{
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Error cannot updated email !');
        }

        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }
    /**
     * Met à jour la description d’un utilisateur.
     *
     * Vérifie les droits d’accès, enregistre la nouvelle description Markdown en base.
     * Affiche un message flash selon le succès ou l’échec.
     *
     * @param UserAccesChecker $uac       Vérifie les autorisations.
     * @param User $user                  Utilisateur à modifier.
     * @param Request $request            Requête contenant la description.
     * @param EntityManagerInterface $em  Gestionnaire d'entités Doctrine.
     *
     * @return Response                   Redirection vers la page de profil.
     */
    #[Route('/updateDescription/{id}', name: 'app_update_description', methods: ['POST'])]
    public function updateDescription(UserAccesChecker $uac, User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!($uac->isConnected($user) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $newDescription = trim($request->request->get('description'));

        if ($newDescription !== null && $user) {
            $user->setDescription($newDescription);
            $em->flush();
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('success', 'Description updated successfully!');
        } else {
            $request->getSession()->getFlashBag()->clear();
            $this->addFlash('error', 'Error: Could not update description.');
        }

        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }

    /**
     * Met à jour l’image de profil d’un utilisateur.
     *
     * Supprime les anciennes images si elles existent, puis déplace la nouvelle dans le répertoire dédié.
     * Utilise `UploadManager` pour nommer et déplacer le fichier.
     * Met à jour le chemin d’accès à l’image dans l’entité utilisateur.
     *
     * @param UploadManager $uploadManager Service chargé de gérer les uploads.
     * @param UserAccesChecker $uac        Vérifie les autorisations.
     * @param User $user                   Utilisateur concerné.
     * @param Request $request             Requête contenant le fichier.
     * @param EntityManagerInterface $em   Gestionnaire d'entités Doctrine.
     * @param SluggerInterface $slugger    Non utilisé ici (optionnel à retirer si inutile).
     *
     * @return Response                    Redirection vers la page de profil.
     */
    #[Route('/update-avatar/{id}', name: 'app_update_avatar', methods: ['POST'])]
    public function updateAvatar(UploadManager $uploadManager, UserAccesChecker $uac, User $user, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if (!($uac->isConnected($user) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        $file = $request->files->get('avatar');

        if ($file && $file->isValid()) {
            // Supprimer les anciens fichiers
            $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/avatar';
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
                $old = $destination . '/' . $user->getId() . '_avatar.' . $ext;
                if (file_exists($old)) {
                    unlink($old);
                }
            }

            // Nom basé sur l'ID utilisateur
            $extension = $file->guessExtension() ?: 'png';
            $customName = $user->getId() . '_avatar.' . $extension;

            // Déplacement via UploadManager
            $savedName = $uploadManager->uploadLocalFile($file, $customName, $destination);

            if ($savedName) {
                $user->setPathImg('/uploads/avatar/' . $savedName);
                $em->flush();
                $this->addFlash('success', 'Image mise à jour avec succès !');
            } else {
                $this->addFlash('error', 'Erreur lors de l’envoi de l’image.');
            }
        } else {
            $this->addFlash('error', 'Image invalide ou absente.');
        }

        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }
    /**
     * Affiche une page invitant l’utilisateur à vérifier son e-mail.
     *
     * Redirige vers l’accueil si l’utilisateur est déjà vérifié.
     *
     * @param UserAccesChecker $uac   Vérifie si l’utilisateur a validé son e-mail.
     *
     * @return Response               Page d’information ou redirection.
     */
    #[Route('/should-verify', name: 'should_verify')]
    public function shouldVerif(UserAccesChecker $uac): Response
    {
        if ($uac->isVerified()) {
            return $this->redirectToRoute('app_home');
        }
        return $this->render('registration/should_verify.html.twig', []);
    }

}
