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
    #[Route('/contact', name: 'app_contact')]
    public function index(
        Request $request,
        MailerInterface $mailer,
        CsrfTokenManagerInterface $csrfTokenManager,
        RateLimiterFactory $contactLimiter
    ): Response {
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
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $message = $request->request->get('message');

            // Création de l'email
            $mail = (new Email())
                ->from($email)
                ->to('charles.lindecker@outlook.fr')
                ->subject('Nouveau message de contact')
                ->text("Nom : $name\nEmail : $email\n\n$message");

            // Envoi
            $mailer->send($mail);

            $this->addFlash('success', 'Votre message a bien été envoyé !');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig');
    }

    #[Route('/terms-service', name: 'app_terms_service')]
    public function termsService(){
        return $this->render('contact/terms-service.html.twig');
    }
    #[Route('/privacy-policy', name: 'app_privacy_policy')]
    public function privacyPolicy(): Response
    {
        return $this->render('contact/privacy-policy.html.twig');
    }
    #[Route('/legal-notice', name: 'app_legal_notice')]
    public function legalNotice(): Response
    {
        return $this->render('contact/legal-notice.html.twig');
    }



}

