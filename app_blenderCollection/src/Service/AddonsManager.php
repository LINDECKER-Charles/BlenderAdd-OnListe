<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AddonsManager
{
    /**
     * Ajoute un add-on à la session s'il n'est pas déjà présent.
     *
     * @param string $url URL de l'add-on (doit venir de extensions.blender.org)
     * @param SessionInterface $session La session utilisateur actuelle
     * @return array Liste mise à jour des add-ons en session
     */
    public function addAddOn(string $url, SessionInterface $session): array
    {
        $addons = $session->get('valid_addons', []);

        // Vérifie si l'add-on est déjà présent
        foreach ($addons as $addon) {
            if ($addon[0] === $url) {
                return $addons; // Ne rien faire si déjà présent
            }
        }
        // Sinon, on l'ajoute
        $addons[] = [$url];
        $session->set('valid_addons', $addons);

        return $addons;
    }

    /**
     * Supprime un add-on de la session à partir de son URL.
     *
     * @param string $url URL de l'add-on à retirer
     * @param SessionInterface $session La session utilisateur actuelle
     * @return array Liste mise à jour des add-ons
     */
    public function suprAddOn(string $url, SessionInterface $session): array
    {
        $addons = $session->get('valid_addons', []);

        // Supprime tous les add-ons correspondant à l'URL
        $addons = array_filter($addons, fn($addon) => $addon[0] !== $url);

        $session->set('valid_addons', array_values($addons)); // Reindexation propre

        return $addons;
    }

    /**
     * Vérifie que l'URL fournie correspond bien à un add-on Blender valide.
     *
     * Exemple d'URL valide :
     * https://extensions.blender.org/add-ons/super-plugin/
     *
     * @param string $url L’URL à valider
     * @return bool true si l’URL est valide et autorisée
     */
    public function isValidAddonUrl(string $url): bool
    {
        if (!preg_match('#^https://extensions\.blender\.org/add-ons/#', $url)) {
            return false;
        }
        $parsed = parse_url($url);
        if (strpos(($parsed['host'] ?? ''), '@') !== false) {
            return false;
        }
        $ip = gethostbyname($parsed['host']);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // IP multicast/broadcast ?
        $octets = array_map('intval', explode('.', $ip));
        if ($octets[0] >= 224 && $octets[0] <= 239) {
            return false;
        }
        return true;
    }

}

