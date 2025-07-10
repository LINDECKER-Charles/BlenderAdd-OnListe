<?php

namespace App\Controller;

use App\Security\EmailVerifier;
use App\Service\MarkdownService;
use App\Repository\UserRepository;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class SecurityController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {

        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/profil', name: 'app_profil')]
    public function profil(MarkdownService $md): Response
    {
        $user = $this->getUser();
        $htmlDescription = $md->toHtml($user->getDescription() ?? '');

        return $this->render('security/profil.html.twig', [
            'user' => $user,
            'descriptionHtml' => $htmlDescription,
        ]);
    }

    #[Route('/updateName', name: 'app_update_name', methods: ['POST'])]
    public function updateName(Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $user = $this->getUser();

        $newName = trim($request->request->get('name'));
        if($userRepository->findOneBy(['name' => $newName])){
            $this->addFlash('error', 'Name is taken!');
            return $this->redirectToRoute('app_profil');
        }
        if ($newName && $user) {
            $user->setName($newName);
            $em->flush();

            $this->addFlash('success', 'Name updated !');
        }else{
            $this->addFlash('error', 'Error cannot updated name !');
        }

        return $this->redirectToRoute('app_profil');
    }

    #[Route('/updateEmail', name: 'app_update_email', methods: ['POST'])]
    public function updateEmail(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $newEmail = trim($request->request->get('name'));
        if ($newEmail && $user) {
            $user->setEmail($newEmail);
            $user->setIsVerified(false);
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('no-reply@blender-collection.com', 'Blender Collection Mail Bot'))
                    ->to((string) $user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );
            $em->flush();

            $this->addFlash('success', 'Email updated !');
            $this->addFlash('warning', 'You need to verify your new email !');
        }else{
            $this->addFlash('error', 'Error cannot updated email !');
        }

        return $this->redirectToRoute('app_profil');
    }

    #[Route('/updateDescription', name: 'app_update_description', methods: ['POST'])]
    public function updateDescription(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $newDescription = trim($request->request->get('description'));

        if ($newDescription !== null && $user) {
            $user->setDescription($newDescription);
            $em->flush();

            $this->addFlash('success', 'Description updated successfully!');
        } else {
            $this->addFlash('error', 'Error: Could not update description.');
        }

        return $this->redirectToRoute('app_profil');
    }


    #[Route('/update-avatar', name: 'app_update_avatar', methods: ['POST'])]
    public function updateAvatar(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        $file = $request->files->get('avatar');

        if ($file && $file->isValid()) {
            // Déduire l'extension (ex: jpg, png, webp)
            $extension = $file->guessExtension() ?: 'png';

            // Nom de fichier basé sur l'ID utilisateur
            $filename = $user->getId() . '_avatar.' . $extension;

            // Chemin absolu vers le dossier de stockage
            $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/avatar';

            // Supprimer les anciens avatars du même utilisateur (toutes extensions confondues)
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
                $old = $destination . '/' . $user->getId() . '_avatar.' . $ext;
                if (file_exists($old)) {
                    unlink($old);
                }
            }

            // Déplacement du fichier
            $file->move($destination, $filename);

            // Enregistrement du chemin relatif en base
            $user->setPathImg('/uploads/avatar/' . $filename);
            $em->flush();

            $this->addFlash('success', 'Image updated!');
        } else {
            $this->addFlash('error', 'Error: could not update image!');
        }

        return $this->redirectToRoute('app_profil');
    }

    #[Route('/should-verify', name: 'should_verify')]
    public function shouldVerif(): Response
    {return $this->render('registration/should_verify.html.twig', []);}

}
