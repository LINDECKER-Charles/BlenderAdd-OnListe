<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AddonsManager
{
    public function addAddOn(string $url, array $data, SessionInterface $session): array
    {
        $addons = $session->get('valid_addons', []);

        // Évite les doublons en supprimant d'abord si déjà présent
        $addons = array_filter($addons, fn($addon) => $addon[0] !== $url);

        // Ajoute l'add-on à la fin
        $addons[] = [$url, $data];

        $session->set('valid_addons', array_values($addons)); // Reindexation propre

        return $addons;
    }
    public function suprAddOn(string $url, SessionInterface $session): array
    {
        $addons = $session->get('valid_addons', []);

        // Supprime tous les add-ons correspondant à l'URL
        $addons = array_filter($addons, fn($addon) => $addon[0] !== $url);

        $session->set('valid_addons', array_values($addons)); // Reindexation propre

        return $addons;
    }

    public function isValidAddonUrl(string $url): bool
    {
        // Vérifie que l'URL est valide et commence bien par le domaine cible
        $parsedUrl = parse_url($url);

        return isset($parsedUrl['scheme'], $parsedUrl['host'], $parsedUrl['path'])
            && $parsedUrl['scheme'] === 'https'
            && $parsedUrl['host'] === 'extensions.blender.org'
            && str_starts_with($parsedUrl['path'], '/add-ons/');
    }

    public function isValidAddonSize(string $url): bool
    {
        // Vérifie que l'URL est valide et commence bien par le domaine cible
        $parsedUrl = parse_url($url);

        return isset($parsedUrl['scheme'], $parsedUrl['host'], $parsedUrl['path'])
            && $parsedUrl['scheme'] === 'https'
            && $parsedUrl['host'] === 'extensions.blender.org'
            && str_starts_with($parsedUrl['path'], '/add-ons/');
    }

}

