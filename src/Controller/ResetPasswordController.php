<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Form\ResetPasswordType;
use Symfony\Component\Mime\Email;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ResetPasswordRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetPasswordController extends AbstractController
{
    private $passwordHasher;
    private $manager;
    private $mailer;

    public function __construct(
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $manager,
        MailerInterface $mailer
    ) {
        $this->passwordHasher = $passwordHasher;
        $this->manager = $manager;
        $this->mailer = $mailer;
    }

    #[Route('/password-reset', name: 'reset_password')]
    public function index(Request $request, UserRepository $repo): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('account');
        }

        if ($request->get('email')) {
            $user = $repo->findOneByEmail($request->get('email'));

            if ($user) {
                $resetPassword = new ResetPassword();
                $resetPassword->setUser($user)
                    ->setToken(bin2hex(random_bytes(32)))
                    ->setCreatedAt(new \DateTimeImmutable);

                $this->manager->persist($resetPassword);
                $this->manager->flush();

                $this->sendResetPasswordEmail($user, $resetPassword);

                $this->addFlash(
                    'success',
                    'A password reset email has been sent to ' . $user->getEmail()
                );
            } else {
                $this->addFlash(
                    'danger',
                    'The email ' . $request->get('email') . ' doesn\'t exist, please sign in'
                );
                return $this->redirectToRoute('register');
            }
        }

        return $this->render('reset_password/resetPassword.html.twig', [
            'controller_name' => 'ResetPasswordController',
        ]);
    }

    #[Route('/edit-password/{token}', name: 'update_password')]
    public function update($token, ResetPasswordRepository $repo, Request $request): Response
    {
        $resetPassword = $repo->findOneByToken($token);

        if (!$resetPassword) {
            $this->addFlash(
                'danger',
                'The link has expired'
            );
            return $this->redirectToRoute('home');
        }

        $dateCreate = $resetPassword->getCreatedAt();
        $now = new \DateTime();

        if ($now > $dateCreate->modify('+1 hour')) {
            $this->addFlash(
                'danger',
                'The link has expired'
            );
            return $this->redirectToRoute('reset_password');
        }

        $user = $resetPassword->getUser();
        $form = $this->createForm(ResetPasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($this->passwordHasher->hashPassword(
                $user,
                $user->getNewPassword()
            ));
            $this->manager->persist($user);
            $this->manager->flush();

            $this->addFlash(
                'success',
                "Your new password has been successfully created"
            );
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/update.html.twig', [
            "form" => $form->createView(),
        ]);
    }

    private function sendResetPasswordEmail($user, ResetPassword $resetPassword): void
    {
        $resetUrl = $this->generateUrl(
            'update_password',
            ['token' => $resetPassword->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->from('noreply@pinkkiwi.com')
            ->to($user->getEmail())
            ->subject('Reset Your PinkKiwi Password')
            ->html($this->renderView('emails/password_reset.html.twig', [
                'user' => $user,
                'resetUrl' => $resetUrl
            ]));

        $this->mailer->send($email);
    }
}
