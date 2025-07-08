<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

class AddonsScraper
{
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

    private function getTags(Crawler $crawler): array
    {
        $tags = [];

        $tagNodes = $crawler->filter('dd.ext-detail-info-tags a.badge-tag');

        if ($tagNodes->count() > 0) {
            $tags = $tagNodes->each(fn ($node) => trim($node->text()));
        }

        return $tags;
    }

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

    private function getFirstImage(Crawler $crawler): ?string
    {
        $imgNode = $crawler->filter('img')->first();

        if ($imgNode->count() > 0) {
            return $imgNode->attr('src');
        }

        return null;
    }

    public function getAddOn(string $url): array
    {
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




}
