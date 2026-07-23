<?php

namespace App\Contact\UI;

use App\Contact\Application\SendContactMessage;
use App\Contact\Domain\ContactMailerException;
use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\Subject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, SendContactMessage $send, LoggerInterface $logger): Response
    {
        $data = new ContactFormData();
        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $send(new ContactMessage(
                    $data->name,
                    $data->email,
                    $data->phone ?: null,
                    Subject::from($data->subject ?? 'general'),
                    $data->message ?? '',
                ));
            } catch (ContactMailerException $e) {
                // L'envoi a échoué (SMTP indisponible…) : on n'expose pas de 500.
                // On ré-affiche le formulaire (données conservées) avec un message clair.
                $logger->error('Échec de l\'envoi du message de contact.', ['exception' => $e]);
                $this->addFlash('error', "Oups, l'envoi a échoué. Merci de réessayer dans un instant ou de nous appeler directement.");

                return $this->render('contact/index.html.twig', ['form' => $form], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
            }

            $this->addFlash('success', 'Merci ! Votre message a bien été envoyé.');

            return $this->redirectToRoute('contact');
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('contact/index.html.twig', ['form' => $form], new Response(status: $status));
    }
}
