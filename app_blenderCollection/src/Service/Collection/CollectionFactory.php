<?php

namespace App\Service\Collection;

use App\Entity\Addon;
use App\Entity\Liste;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class CollectionFactory
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Crée une instance de Liste prête à être persistée.
     *
     * @param string $name
     * @param string|null $description
     * @param bool $isVisible
     * @param array $addons Tableau de [$url, bool, array] venant de la session
     * @param User $user
     * @return Liste
     */
    public function create(string $name, ?string $description, bool $isVisible, array $addons, User $user): Liste {
        $liste = new Liste();
        $liste->setName($name);
        $liste->setDescription($description);
        $liste->setIsVisible($isVisible);
        $liste->setDateCreation(new \DateTime());
        $liste->setDownload(0);
        $liste->setUsser($user);

        foreach ($addons as [$url]) {
            if (isset($url)) {
                $addon = $this->em->getRepository(Addon::class)->findOneBy(['idBlender' => $url]);

                if (!$addon) {
                    $addon = new Addon();
                    $addon->setIdBlender($url);
                    $this->em->persist($addon);
                }

                $liste->addAddon($addon);
            }
        }

        return $liste;
    }
}
