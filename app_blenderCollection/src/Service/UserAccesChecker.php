<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\User;
use App\Entity\Liste;
use App\Entity\SousPost;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class UserAccesChecker
{

    private RouterInterface $router;

    private ?UserInterface $user;

    public function __construct(RouterInterface $router, TokenStorageInterface $tokenStorage) 
    {
        $this->router = $router;
        $token = $tokenStorage->getToken();
        $this->user = $token ? $token->getUser() : null;
    }
    /**
     * Vérifie si l'utilisateur est connecté.
     *
     * Si aucun utilisateur n'est passé en paramètre, la méthode utilise celui stocké lors de l'instanciation du service.
     *
     * @param User|null $user L'utilisateur à vérifier (facultatif).
     * @return bool True si l'utilisateur est connecté, sinon False.
     */
    public function isConnected(?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return $user !== null;
    }
    /**
     * Vérifie si l'utilisateur est connecté ET a confirmé son adresse email.
     *
     * @param User|null $user L'utilisateur à vérifier (facultatif).
     * @return bool True si l'utilisateur est connecté et vérifié, sinon False.
     */
    public function isVerified(?User $user = null): bool{
        $user = $user ?? $this->user;
        if(!$this->isConnected($user)){
            return false;
        }
        return $user->isVerified();
    }
    /**
     * Vérifie si l'utilisateur possède un rôle donné.
     * @param User   $user L'utilisateur à tester.
     * @param string $role Le rôle à vérifier (ex. : ROLE_ADMIN, ROLE_USER).
     * @return bool True si l'utilisateur possède le rôle.
     */
    public function hasRole(?User $user = null, string $role): bool
    {
        $user = $user ?? $this->user;
        if(!($this->isConnected($user))){
            return false;
        }
        return in_array($role, $user->getRoles(), true);
    }

    /**
     * Vérifie si l'utilisateur est administrateur.
     * @param User $user
     * @return bool
     */
    public function isAdmin(?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return $this->hasRole($user, 'ADMIN');
    }

    /**
     * Vérifie si l'utilisateur est modérateur.
     * @param User $user
     * @return bool
     */
    public function isModerator(?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return $this->hasRole($user, 'MODO');
    }

    /**
     * Vérifie si l'utilisateur est administrateur ou modérateur.
     *
     * @param User $user
     * @return bool
     */
    public function isStaff(?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return ($this->hasRole($user, 'ADMIN') || $this->hasRole($user, 'MODO')) && $this->isVerified($user);
    }

    /**
     * Vérifie si l'utilisateur est banni.
     * @param User $user
     * @return bool
     */
    public function isBanned(?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return $this->hasRole($user, 'BAN');
    }

    /**
     * Vérifie si l'utilisateur est verrouillé.
     * @param User $user
     * @return bool
     */
    public function isLocked(?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return $this->hasRole($user, 'LOCK');
    }


    /**
     * Vérifie si l'utilisateur est autorisé (ni LOCK ni BAN).
     * @param User $user
     * @return bool
     */
    public function isAllowed(?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return !$this->isLocked($user) && !$this->isBanned($user);
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'une liste.
     * @param User  $user
     * @param Liste $liste
     * @return bool
     */
    public function isOwnerOfListe(Liste $liste, ?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return $user === $liste->getUsser() && $this->isAllowed($user) && $this->isVerified($user);
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'un post.
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function isOwnerOfPost(Post $post, ?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return $user === $post->getCommenter() && $this->isAllowed($user) && $this->isVerified($user);
    }

    /**
     * Vérifie si l'utilisateur est propriétaire d'une réponse (SousPost).
     * @param User     $user
     * @param SousPost $sousPost
     * @return bool
     */
    public function isOwnerOfSousPost(SousPost $sousPost, ?User $user = null): bool
    {
        $user = $user ?? $this->user;
        return $user === $sousPost->getCommenter() && $this->isAllowed($user) && $this->isVerified($user);
    }

    /**
     * Gère les redirections globales selon l'état de l'utilisateur :
     * - Redirige vers la page de connexion s'il n'est pas connecté.
     * - Redirige vers la page de vérification s'il n'est pas encore vérifié.
     * - Redirige vers la page de profil s'il n'est pas staff (admin ou modo).
     * - Sinon, redirige vers la page d'accueil.
     *
     * @param User|null $user L'utilisateur à évaluer (optionnel, récupéré automatiquement sinon).
     * @return RedirectResponse La redirection appropriée.
     */
    public function redirectingGlobal(?User $user = null): RedirectResponse
    {
        $user = $user ?? $this->user;
        /* dd($user); */
        /* dd($this->isConnected($user)); */
        if (!$this->isConnected($user)) {
            return new RedirectResponse($this->router->generate('app_login'));
        }

        if (!$this->isVerified($user)) {
            return new RedirectResponse($this->router->generate('should_verify'));
        }

        if (!$this->isStaff($user)) {
            return new RedirectResponse($this->router->generate('app_profil'));
        }

        return new RedirectResponse($this->router->generate('app_home'));
    }

    /**
     * Vérifie l'état global de l'utilisateur et renvoie une JsonResponse si bloqué.
     *
     * @param User|null $user
     * @return JsonResponse|null
     */
    public function redirectingGlobalJson(?User $user = null): ?JsonResponse
    {
        $user = $user ?? $this->user;

        if (!$this->isConnected($user)) {
            return new JsonResponse(['error' => 'User not authenticated.'], 401);
        }
        if (!$this->isVerified($user)) {
            return new JsonResponse(['error' => 'User not verified.'], 403);
        }
        if ($this->isLocked($user)) {
            return new JsonResponse(['error' => 'Access denied: your account is temporarily locked.'], 403);
        }
        if ($this->isBanned($user)) {
            return new JsonResponse(['error' => 'Access denied: your account has been banned.'], 403);
        }
        return null;
    }
}
