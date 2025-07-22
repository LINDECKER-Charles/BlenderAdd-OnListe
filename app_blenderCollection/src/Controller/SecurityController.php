<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UploadManager;
use App\Security\EmailVerifier;
use App\Service\MarkdownService;
use App\Repository\LogRepository;
use App\Service\UserAccesChecker;
use App\Repository\UserRepository;
use App\Repository\ListeRepository;
use Symfony\Component\Mime\Address;
use App\Service\Profil\ProfilEditor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class SecurityController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private readonly ProfilEditor $profilEditor,
        private readonly UserAccesChecker $uac,
        private readonly MarkdownService $md
        ){}

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
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->uac->isConnected()) {
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
    public function profil(): Response
    {
        if (!$this->uac->isConnected()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        
        return $this->render('security/profil.html.twig', [
            'user' => $user,
            'descriptionHtml' => $this->md->toHtml($user->getDescription() ?? ''),
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
    public function profilVisiteur(?User $user): Response
    {
        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/profil.html.twig', [
            'user' => $user,
            'descriptionHtml' => $this->md->toHtml($user->getDescription() ?? ''),
        ]);
    }
    /**
     * Met à jour le nom d’un utilisateur (via formulaire).
     *
     * Vérifie les droits (soit l’utilisateur lui-même, soit un staff).
     * Empêche l’usage d’un nom déjà existant, puis met à jour la base.
     * Affiche des messages flash selon le résultat.
     *
     * @param User $user                Utilisateur à modifier.
     * @param Request $request          Requête contenant le nouveau nom.
     *
     * @return Response                 Redirection vers la page de profil.
     */
    #[Route('/updateName/{id}', name: 'app_update_name', methods: ['POST'])]
    public function updateName(User $user, Request $request): Response
    {
        if (!($this->uac->isConnected($user) || $this->uac->isStaff())) {
            return $this->uac->redirectingGlobal();
        }

        $this->profilEditor->updateName($user, trim($request->request->get('name')));

        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }

    /**
     * Met à jour l’adresse e-mail de l’utilisateur et déclenche un e-mail de confirmation.
     *
     * Remet le compte en non-vérifié et envoie un lien de validation à la nouvelle adresse.
     * Affiche des messages flash selon la réussite de l’opération.
     *
     * @param User $user                    Utilisateur concerné.
     * @param Request $request              Requête contenant le nouvel e-mail.
     *
     * @return Response                     Redirection vers la page de profil.
     */
    #[Route('/updateEmail/{id}', name: 'app_update_email', methods: ['POST'])]
    public function updateEmail(User $user, Request $request): Response
    {
        if (!($this->uac->isConnected($user) || $this->uac->isStaff())) {
            return $this->uac->redirectingGlobal();
        }

        $this->profilEditor->updateEmail($user, trim($request->request->get('email')));

        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }
    /**
     * Met à jour la description d’un utilisateur.
     *
     * Vérifie les droits d’accès, enregistre la nouvelle description Markdown en base.
     * Affiche un message flash selon le succès ou l’échec.
     *
     * @param User $user                  Utilisateur à modifier.
     * @param Request $request            Requête contenant la description.
     *
     * @return Response                   Redirection vers la page de profil.
     */
    #[Route('/updateDescription/{id}', name: 'app_update_description', methods: ['POST'])]
    public function updateDescription(User $user, Request $request): Response
    {
        if (!($this->uac->isConnected($user) || $this->uac->isStaff())) {
            return $this->uac->redirectingGlobal();
        }

        $this->profilEditor->updateDescription($user, trim($request->request->get('description')));

        return $this->redirectToRoute('app_profil_visiteur', ['id' => $user->getId()]);
    }


    /**
     * Met à jour l’image de profil d’un utilisateur.
     *
     * Supprime les anciennes images si elles existent, puis déplace la nouvelle dans le répertoire dédié.
     * Utilise `UploadManager` pour nommer et déplacer le fichier.
     * Met à jour le chemin d’accès à l’image dans l’entité utilisateur.
     *
     * @param User $user                   Utilisateur concerné.
     * @param Request $request             Requête contenant le fichier.
     *
     * @return Response                    Redirection vers la page de profil.
     */
    #[Route('/update-avatar/{id}', name: 'app_update_avatar', methods: ['POST'])]
    public function updateAvatar(User $user, Request $request): Response
    {
        if (!($this->uac->isConnected($user) || $this->uac->isStaff())) {
            return $this->uac->redirectingGlobal();
        }

        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/avatar';
        $file = $request->files->get('avatar');

        $this->profilEditor->updateAvatar($user, $file, $destination);

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

    /**
     * Supprime le compte de l'utilisateur connecté et le déconnecte.
     *
     * Vérifie que l'utilisateur est bien connecté. Utilise le service ProfilEditor
     * pour supprimer le compte, invalider la session et déconnecter l'utilisateur.
     * Affiche un message flash et redirige vers la page d'accueil.
     *
     * @param UserAccesChecker $uac         Service de vérification d’accès utilisateur.
     * @param ProfilEditor     $editor      Service de gestion du profil utilisateur.
     *
     * @return RedirectResponse             Redirection vers l’accueil après suppression.
     */
    #[Route('/delete-account/{id}', name: 'delete_account', methods: ['POST'])]
    public function deleteAccount(Request $request, UserAccesChecker $uac, ProfilEditor $editor, User $user): RedirectResponse
    {
        if (!($uac->isConnected($user) || $uac->isStaff())) {
            return $uac->redirectingGlobal();
        }

        if (!$this->isCsrfTokenValid('delete_account_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $uac->redirectingGlobal($user);
        }

        if ($uac->isStaff()) {
            $editor->deleteUser($user);

            $this->addFlash('success', 'Le compte a été supprimé avec succès.');

            $referer = $request->headers->get('referer');
            $referer = filter_var($referer, FILTER_VALIDATE_URL) ? $referer : null;
            return $referer
                ? new RedirectResponse($referer)
                : $this->redirectToRoute('admin_users');
        }

        $editor->deleteUserAndLogout($user);
        $this->addFlash('success', 'Votre compte a été supprimé avec succès.');

        return $this->redirectToRoute('app_login');
    }



}
