<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegisterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $manager,
    ) {}


    #[Route('/inscription', name: 'register')]
    public function index(Request $request): Response
    {
        $user = new User();

        $form = $this->createForm(RegisterType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $user->getPassword()
            );
            $user->setPassword($hashedPassword);

            $user->setActive(true);
            $this->manager->persist($user);
            $this->manager->flush();

            $this->addFlash(
                'success',
                'The account ' . $user->getEmail() . ' has been created successfully'
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->render('register/register.html.twig', [
            'form' => $form->createView()
        ]);
    }


    #[Route('/inscription/{id}/{token}', name: 'registerActivation')]
    public function activation(User $user, string $token): Response
    {
        if (!$user->isActive()) {

            $verifToken = hash_hmac('sha256', $user->getEmail() . $user->getId(), $this->getParameter('kernel.secret'));

            if (hash_equals($verifToken, $token)) {

                $user->setActive(true);
                $this->manager->flush();

                $this->addFlash('success', 'Account successfully activated');
                return $this->redirectToRoute('account');
            }

            $this->addFlash('danger', 'Incorrect link');
            return $this->redirectToRoute('account');
        }

        $this->addFlash('success', 'The account ' . $user->getEmail() . ' is already activated');
        return $this->redirectToRoute('account');
    }
}
