<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

class AddonsScraper
{
    /**
     * Récupère le titre principal de l'add-on à partir de la balise <h1>.
     *
     * @param Crawler $crawler Le DOM crawler de la page HTML de l'add-on
     * @return string Le titre extrait, ou "No title" si absent
     */
    private function getTitleAddOn(Crawler $crawler): string
    {
        $h1 = $crawler->filter('h1.d-flex')->first();

        if ($h1->count() === 0) {
            return 'No title';
        }

        $text = '';
        foreach ($h1->getNode(0)->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text .= $node->nodeValue;
            }
        }
        return trim($text);
    }

    /**
     * Extrait les tags associés à l'add-on (ex : catégories ou labels).
     *
     * @param Crawler $crawler Le DOM crawler de la page HTML de l'add-on
     * @return string[] Tableau de tags extraits
     */
    private function getTags(Crawler $crawler): array
    {
        $tags = [];

        $tagNodes = $crawler->filter('dd.ext-detail-info-tags a.badge-tag');

        if ($tagNodes->count() > 0) {
            $tags = $tagNodes->each(fn ($node) => trim($node->text()));
        }

        return $tags;
    }

    /**
     * Récupère la taille du fichier de l'add-on (ex : "2.1 MB").
     *
     * @param Crawler $crawler Le DOM crawler de la page HTML de l'add-on
     * @return string|null La taille formatée ou null si absente
     */
    private function getSize(Crawler $crawler): ?string
    {
        $dtNodes = $crawler->filter('dt');

        foreach ($dtNodes as $dt) {
            if (trim($dt->textContent) === 'Size') {
                $dd = $dt->nextSibling;

                // On saute les textes vides ou espaces éventuels
                while ($dd && ($dd->nodeType !== XML_ELEMENT_NODE || strtolower($dd->nodeName) !== 'dd')) {
                    $dd = $dd->nextSibling;
                }

                if ($dd && strtolower($dd->nodeName) === 'dd') {
                    return trim($dd->textContent);
                }
            }
        }

        return null;
    }

    /**
     * Récupère l'URL de la première image associée à l'add-on (généralement un aperçu).
     *
     * @param Crawler $crawler Le DOM crawler de la page HTML de l'add-on
     * @return string|null L'URL complète de l'image ou null si absente
     */
    private function getFirstImage(Crawler $crawler): ?string
    {

        $imgNode = $crawler->filter('a.galleria-item img')->first();

        if ($imgNode->count() > 0) {
            $src = $imgNode->attr('src');

            // Si l'URL est relative, on la rend absolue
            if (str_starts_with($src, '/')) {
                $src = 'https://extensions.blender.org' . $src;
            }
            return $src;
        }
        return null;
    }

    /**
     * Récupère uniquement l'URL de l'image principale d'un add-on depuis son URL.
     *
     * @param string $url URL de la page de l'add-on sur extensions.blender.org
     * @return string|null L'URL absolue de l'image ou null si aucune trouvée
     */
    public function getAddOnImage(string $url): ?string
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $url);
        $html = $response->getContent();

        $crawler = new Crawler($html);

        return $this->getFirstImage($crawler);
    }
    
    /**
     * Récupère les informations principales d’un add-on Blender depuis son URL.
     *
     * @param string $url URL complète de la page de l’add-on sur extensions.blender.org
     * @return array{
     *     title: string,
     *     tags: string[],
     *     size: string|null,
     *     image: string|null
     * }
     */
    public function getAddOn(string $url): array
    {
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if (!$url) {
            throw new \InvalidArgumentException('URL invalide.');
        }

        $host = parse_url($url, PHP_URL_HOST);
        $ip = gethostbyname($host);
        if (isPrivateIp($ip)) {
            throw new \Exception('Accès interdit à une IP privée.');
        }
        // Liste des IP interdites (privées, locales, etc.)
        $blacklist = [
            '127.0.0.1',     // localhost
            '::1',           // IPv6 localhost
            '0.0.0.0',
            '169.254.169.254', // AWS metadata
            'localhost',
        ];

        foreach ($blacklist as $forbidden) {
            if ($ip === $forbidden || $host === $forbidden) {
                throw new \Exception('Accès interdit à cette ressource interne.');
            }
        }

        $client = HttpClient::create();
        $response = $client->request('GET', $url);
        $html = $response->getContent();

        $crawler = new Crawler($html);

        return [
            'title' => $this->getTitleAddOn($crawler),
            'tags' => $this->getTags($crawler),
            'size' => $this->getSize($crawler),
            'image' => $this->getFirstImage($crawler),
        ];
    }
    
    /**
     * Vérifie si une adresse IP est privée ou réservée (ex : LAN, localhost, etc.).
     *
     * @param string $ip Adresse IP à analyser (ex : '192.168.0.1').
     * @return bool true si l’IP est privée ou réservée, false sinon.
     */
    function isPrivateIp($ip) {
        return
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

}
