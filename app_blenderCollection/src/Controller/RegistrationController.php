<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationForm;
use App\Security\EmailVerifier;
use Anhskohbo\NoCaptcha\NoCaptcha;
use Symfony\Component\Mime\Address;
use Symfony\Component\Form\FormError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    /**
     * Gère l'inscription d'un nouvel utilisateur.
     *
     * Fonctionnalités incluses :
     * - Redirection si l'utilisateur est déjà connecté
     * - Création du formulaire d'inscription et liaison avec un nouvel utilisateur
     * - Vérification de la correspondance entre les mots de passe
     * - Validation du mot de passe selon les recommandations CNIL
     * - Hachage et enregistrement du mot de passe
     * - Attribution automatique du rôle "USER"
     * - Connexion automatique de l'utilisateur après inscription
     * - Envoi d'un email de confirmation avec lien sécurisé
     *
     * @param Request $request La requête HTTP en cours
     * @param UserPasswordHasherInterface $userPasswordHasher Service de hachage de mot de passe
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine
     * @param Security $security Service de gestion de la session utilisateur
     * @return Response
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, Security $security): Response
    {
        //Verification utilisateur deja connecte
        if ($this->getUser()){
            return $this->redirectToRoute('app_home');
        }

        //Creation nouvelle utilisateur et injection des données
        $user = new User();
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $confirm  = $form->get('confirmPassword')->getData();
            
            if($plainPassword !== $confirm){ //Correspondance mdp et confirme mdp
                $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Le mot de passe confirmé est différent.'));
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/', $plainPassword)) { //Check rejex CNIL
                $form->get('plainPassword')->addError(
                    new FormError('Mot de passe trop faible. Il doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.')
                );
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]); 
            }
            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $user->addRole('USER');
            $entityManager->persist($user);
            $entityManager->flush();

            //Log l'utilisateur
            $security->login($user);

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('no-reply@blender-collection.com', 'Blender Collection Mail Bot'))
                    ->to((string) $user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // do anything else you need here, like send an email

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Vérifie l'adresse email d’un utilisateur via un lien sécurisé.
     *
     * Fonctionnalités incluses :
     * - Vérifie que l'utilisateur est bien authentifié
     * - Valide le lien signé envoyé par email
     * - Active le compte en mettant à jour le champ `isVerified`
     * - Affiche un message flash en cas d'erreur ou de succès
     *
     * @param Request $request La requête contenant les paramètres du lien
     * @param TranslatorInterface $translator Service de traduction pour les messages d'erreur
     * @return Response
     */
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            /** @var User $user */
            $user = $this->getUser();
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Your email address has been verified.');

        return $this->render('registration/verify_email.html.twig');
    }
}
