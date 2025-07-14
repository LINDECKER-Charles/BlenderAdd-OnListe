<?php

namespace App\Service\Collection;

use App\Entity\Liste;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CollectionCreator
{
    public function __construct(
        private readonly CollectionValidator $validator,
        private readonly CollectionFactory $factory,
        private readonly CollectionImageManager $imageManager,
        private readonly CollectionZipDispatcher $zipDispatcher,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack
    ) {}

    /**
     * Crée une collection complète à partir d'une requête POST.
     *
     * @param Request $request
     * @param User $user
     * @return Liste
     * @throws \InvalidArgumentException en cas d'erreur métier
     */
    public function handle(Request $request, User $user): Liste
    {
        $session = $this->requestStack->getSession();
        $addons = $session->get('valid_addons', []);

        $rawName = $request->request->get('fullName');
        $rawDescription = $request->request->get('description');
        $isVisible = $request->request->getBoolean('isVisible');

        // Supprime balises + entités + espace parasite
        $cleanName = trim(strip_tags(html_entity_decode($rawName ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $cleanDescription = $rawDescription !== null
            ? trim(strip_tags(html_entity_decode($rawDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            : null;

        // Si après nettoyage il reste du HTML ou des caractères suspects → on invalide
        $name = $this->isSafeText($cleanName) ? $cleanName : null;
        $description = $cleanDescription !== null && !$this->isSafeText($cleanDescription) ? null : $cleanDescription;


        // Étapes métiers
        $this->validator->validateNameIsUnique($name);
        $this->validator->validateAddonLimit($addons);

        $liste = $this->factory->create($name, $description, $isVisible, $addons, $user);

        $image = $this->imageManager->resolveImage($request, $addons);
        if ($image) {
            $liste->setImage($image);
        }

        $this->em->persist($liste);
        $this->zipDispatcher->dispatchZipGeneration($liste);
        $this->em->flush();

        $session->remove('valid_addons');

        return $liste;
    }


    private function isSafeText(string $text): bool
    {
        if ($text === '') {
            return false;
        }
        // Aucune balise ne doit subsister
        if (preg_match('/<[^>]*>/', $text)) {
            return false;
        }

        // Pas de script, iframe, etc. même partiellement encodés
        $lower = strtolower($text);
        if (str_contains($lower, 'script') || str_contains($lower, 'iframe') || str_contains($lower, 'onerror') || str_contains($lower, 'onload')) {
            return false;
        }

        return true;
    }
}
