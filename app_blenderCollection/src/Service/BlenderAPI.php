<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class BlenderAPI
{
    private const API_URL = 'https://extensions.blender.org/api/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache
    ) {}

    /**
     * Récupère toutes les extensions avec mise en cache.
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

    public function findExtensionByWebsite(string $websiteUrl): ?array
    {
        /* dd($this->getExtensions()['data']); */
        foreach ($this->getExtensions()['data'] as $extension) {
            if (isset($extension['website']) && $extension['website'] === $websiteUrl) {
                return $extension;
            }
        }
        return null;
    }

}
