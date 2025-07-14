<?php

namespace App\Service\Collection;

use App\Entity\Liste;
use Doctrine\ORM\EntityManagerInterface;

class CollectionValidator
{
    private EntityManagerInterface $em;
    private const MAX_ADDONS = 50;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Vérifie que le nom de la collection est unique.
     *
     * @param string $name
     * @throws \InvalidArgumentException si le nom existe déjà.
     */
    public function validateNameIsUnique(string $name): void
    {
        $existing = $this->em->getRepository(Liste::class)->findOneBy(['name' => $name]);

        if ($existing !== null) {
            throw new \InvalidArgumentException("Une collection porte déjà ce nom. Choisissez-en un autre.");
        }
    }

    /**
     * Vérifie que le nombre d'add-ons est valide (entre 1 et MAX_ADDONS).
     *
     * @param array $addons Liste de tableaux contenant [url, ...]
     * @throws \InvalidArgumentException si la liste est vide ou trop grande.
     */
    public function validateAddonLimit(array $addons): void
    {
        if (empty($addons)) {
            throw new \InvalidArgumentException("Impossible de créer une collection sans add-on.");
        }

        if (count($addons) > self::MAX_ADDONS) {
            throw new \InvalidArgumentException("Vous ne pouvez pas ajouter plus de " . self::MAX_ADDONS . " add-ons à une collection.");
        }
    }
}
