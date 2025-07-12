<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service pour interagir avec l’API officielle de Blender Extensions.
 *
 * Ce service permet de récupérer et de mettre en cache la liste des add-ons
 * disponibles sur https://extensions.blender.org/api/v1.
 */
class BlenderAPI
{
    private const API_URL = 'https://extensions.blender.org/api/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache
    ) {}

    /**
     * Récupère la liste complète des extensions Blender depuis l’API officielle.
     *
     * Les résultats sont mis en cache pendant 10 minutes pour limiter les appels réseau.
     *
     * @return array Liste des extensions au format tableau associatif
     */
    public function getExtensions(): array
    {
        return $this->cache->get('blender_extensions_list', function (ItemInterface $item) {
            $item->expiresAfter(600);
            $response = $this->httpClient->request('GET', self::API_URL . '/extensions');
            $content = $response->toArray();
            return $content ?? [];
        });
    }

    /**
     * Recherche une extension dans la liste par son URL de site officiel.
     *
     * @param string $websiteUrl L’URL du site de l’extension (ex : https://extensions.blender.org/add-ons/nodepie/)
     * @return array|null Les données de l’extension trouvée, ou null si aucune correspondance
     */
    public function findExtensionByWebsite(string $websiteUrl): ?array
    {
        foreach ($this->getExtensions()['data'] as $extension) {
            if (isset($extension['website']) && $extension['website'] === $websiteUrl) {
                return $extension;
            }
        }
        return null;
    }

}
