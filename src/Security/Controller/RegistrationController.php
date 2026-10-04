<?php

namespace App\Security\Controller;

use App\Security\Entity\User;
use App\Security\Form\RegistrationFormType;
use App\Security\UserRegistration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserRegistration $registration, Security $security): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();
            $adopted = $registration->register($user, (string) $form->get('plainPassword')->getData());

            $this->addFlash('success', 'Bienvenue sur TradeLens ! Votre compte est créé.');
            if ($adopted > 0) {
                $this->addFlash('info', sprintf(
                    '%d compte%s de trading déjà présent%s dans la base %s été rattaché%s à votre profil.',
                    $adopted,
                    $adopted > 1 ? 's' : '',
                    $adopted > 1 ? 's' : '',
                    $adopted > 1 ? 'ont' : 'a',
                    $adopted > 1 ? 's' : '',
                ));
            }

            // Connecte l'utilisateur juste après l'inscription.
            return $security->login($user, 'form_login', 'main');
        }

        // 422 si le formulaire est refusé, requis par Turbo.
        return $this->render('security/register.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
