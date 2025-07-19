<?php

namespace App\Service\Collection;

use App\Entity\User;
use App\Entity\Liste;
use App\Service\AdminLogger;
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
        private readonly RequestStack $requestStack,
        private readonly AdminLogger $logger,
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
        if(!$rawName || $rawName === '') {
            $rawName = $user->getName() . '_collection';
        }
        $rawDescription = $request->request->get('description');
        $isVisible = $request->request->getBoolean('isVisible');

        // Supprime balises + entités + espace parasite
        $name = trim(strip_tags(html_entity_decode($rawName ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $description = $rawDescription !== null
            ? trim(strip_tags(html_entity_decode($rawDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            : null;
        

        // Étapes métiers
        $this->validator->validateNameIsUnique($name);
        $this->validator->validateAddonLimit($addons);

        $liste = $this->factory->create($name, $description, $isVisible, $addons, $user);
        // Log de création
        $this->logger->log(
            'Create Collection',
            $user,
            $liste->getName() . ' #' . $liste->getId(),
            'Création de la collection "' . $liste->getName() . '" par ' . $user->getName()
        );
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

}
