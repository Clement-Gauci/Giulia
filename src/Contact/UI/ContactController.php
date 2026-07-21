<?php

namespace App\Contact\UI;

use App\Contact\Application\SendContactMessage;
use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\Subject;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, SendContactMessage $send): Response
    {
        $data = new ContactFormData();
        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $send(new ContactMessage(
                $data->name,
                $data->email,
                $data->phone ?: null,
                Subject::from($data->subject ?? 'general'),
                $data->message ?? '',
            ));
            $this->addFlash('success', 'Merci ! Votre message a bien été envoyé.');

            return $this->redirectToRoute('contact');
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('contact/index.html.twig', ['form' => $form], new Response(status: $status));
    }
}
