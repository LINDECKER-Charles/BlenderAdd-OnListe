<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AddonsManager
{
    public function cleanSession(array $addons, SessionInterface $session): array
    {
        $validated = array_filter($addons, function ($addon) {
            return isset($addon[1]) && $addon[1] === true;
        });

        $session->set('valid_addons', array_values($validated));
        return array_values($validated);
    }
    public function addAddOn(string $url, SessionInterface $session): array
    {
        $addons = $session->get('valid_addons', []);

        foreach ($addons as $index => $addon) {
            if (isset($addon[0]) && $addon[0] === $url) {
                $addons[$index][1] = true;
                break;
            }
        }

        $session->set('valid_addons', $addons);

        return $addons;
    }
    public function suprAddOn(string $url, SessionInterface $session): array
    {
        $addons = $session->get('valid_addons', []);

        foreach ($addons as $index => $addon) {
            if (isset($addon[0]) && $addon[0] === $url) {
                $addons[$index][1] = false;
                break;
            }
        }

        $session->set('valid_addons', $addons);

        return $addons;
    }
    public function addStack(SessionInterface $session, $data, $url){
        $addons = $session->get('valid_addons', []);

        $alreadyExists = false;
        foreach ($addons as $addon) {
            if (is_array($addon) && $addon[0] === $url) {
                $alreadyExists = true;
                break;
            }
        }

        if (!$alreadyExists) {
            $addons[] = [$url, false, $data];
            $session->set('valid_addons', $addons);
        }
    }
}

