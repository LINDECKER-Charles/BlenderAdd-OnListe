<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class RoleManager
{
    public function __construct(private EntityManagerInterface $em) {}
    /**
     * Ajoute un rôle à un utilisateur sans écraser les rôles existants.
     * Rôles typiquement "MODO" ou "ADMIN".
     *
     * @param User $user L'utilisateur auquel ajouter un rôle.
     * @param string $role Le rôle à ajouter (ex: "ROLE_MODO").
     * 
     * @return void
     */
    private function giveRole(User $user, string $role): void
    {
        $role = strtoupper($role);
        $roles = $user->getRoles();

        if (!in_array($role, $roles, true)) {
            $roles[] = $role;
            $user->setRoles($roles);
        }
        $this->em->flush();
    }

    /**
     * Remplace tous les rôles de l’utilisateur par un seul rôle.
     * Rôles typiquement "BAN" ou "LOCK".
     *
     * @param User $user L'utilisateur dont on écrase les rôles.
     * @param string $role Le rôle unique à assigner (ex: "ROLE_BAN").
     * 
     * @return void
     */
    private function setRole(User $user, string $role): void
    {
        $role = strtoupper($role);
        $user->setRoles([$role]);
        $this->em->flush();
    }

    /**
     * Ajoute le rôle "ROLE_MODO" à un utilisateur sans écraser ses rôles existants.
     *
     * @param User $user L'utilisateur à modifier.
     * 
     * @return void
     */
    public function giveModo(User $user): void
    {
        $this->giveRole($user, 'MODO');
    }

    /**
     * Remplace tous les rôles de l’utilisateur par le rôle "ROLE_BAN".
     *
     * @param User $user L'utilisateur à modifier.
     * 
     * @return void
     */
    public function setBan(User $user): void
    {
        $this->setRole($user, 'BAN');
    }

    /**
     * Remplace tous les rôles de l’utilisateur par le rôle "ROLE_LOCK".
     *
     * @param User $user L'utilisateur à modifier.
     * 
     * @return void
     */
    public function setLock(User $user): void
    {
        $this->setRole($user, 'LOCK');
    }

    /**
     * Réinitialise tous les rôles d’un utilisateur, en retirant tous les rôles assignés.
     *
     * @param User $user L'utilisateur dont on veut retirer tous les rôles.
     * 
     * @return void
     */
    public function resetRoles(User $user): void
    {
        $user->setRoles([]);
        $this->em->flush();
    }
}
