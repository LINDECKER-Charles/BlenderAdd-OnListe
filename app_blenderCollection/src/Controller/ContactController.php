<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ContactController extends AbstractController
{
    /**
     * Gère l'affichage et le traitement du formulaire de contact.
     *
     * Fonctionnalités incluses :
     * - Vérification de la méthode POST pour déclencher l'envoi
     * - Protection contre les abus via un rate limiter basé sur l'IP
     * - Vérification de la validité du token CSRF pour éviter les attaques
     * - Récupération et traitement des données du formulaire (nom, email, message)
     * - Envoi d’un email vers l’adresse du support
     * - Gestion des messages flash pour informer l’utilisateur du résultat
     *
     * Redirige vers la page de contact après traitement.
     *
     * @param Request $request La requête HTTP contenant les données du formulaire
     * @param MailerInterface $mailer Le service d'envoi d'email
     * @param CsrfTokenManagerInterface $csrfTokenManager Le gestionnaire CSRF pour valider le token
     * @param RateLimiterFactory $contactLimiter Le rate limiter utilisé pour limiter les envois
     * @return Response La réponse HTTP affichant la page ou redirigeant après traitement
     */
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer, CsrfTokenManagerInterface $csrfTokenManager, RateLimiterFactory $contactLimiter): Response {
        if ($request->isMethod('POST')) {
            
            // Vérification rate limiter
            $limiter = $contactLimiter->create($request->getClientIp());
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives. Veuillez patienter quelques instants.');
                return $this->redirectToRoute('app_contact');
            }

            // Vérification du token CSRF
            $submittedToken = $request->request->get('_csrf_token');
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('contact_form', $submittedToken))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_contact');
            }

            // Récupération des données
            function sanitizeHeaderInput(string $value): string {
                return str_replace(["\r", "\n"], '', $value);
            }

            $name = sanitizeHeaderInput(strip_tags(trim($request->request->get('name'))));
            $email = sanitizeHeaderInput($request->request->get('email'));
            $message = strip_tags(trim($request->request->get('message')));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Email invalide.");
            }

            $text = sprintf(
                "Nom : [%s]\nEmail : [%s]\n\n%s",
                $name,
                $email,
                $message
            );

            $mail = (new Email())
                ->from($email)
                ->to('charles.lindecker@outlook.fr')
                ->subject('Nouveau message de contact')
                ->text($text);

            // Envoi
            $mailer->send($mail);

            $this->addFlash('success', 'Votre message a bien été envoyé !');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig');
    }

    /**
     * Affiche la page des conditions d'utilisation.
     */
    #[Route('/terms-service', name: 'app_terms_service')]
    public function termsService()
    {return $this->render('contact/terms-service.html.twig');}
    #[Route('/privacy-policy', name: 'app_privacy_policy')]
    public function privacyPolicy(): Response
    {return $this->render('contact/privacy-policy.html.twig');}
    #[Route('/legal-notice', name: 'app_legal_notice')]
    public function legalNotice(): Response
    {return $this->render('contact/legal-notice.html.twig');}

}

