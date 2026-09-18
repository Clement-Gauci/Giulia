<?php

namespace App\Contact\UI;

use App\Contact\AntiSpam\Decision;
use App\Contact\AntiSpam\FormSignature;
use App\Contact\AntiSpam\SubmissionGuard;
use App\Contact\Application\SendContactMessage;
use App\Contact\Domain\ContactMailerException;
use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\Subject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        SendContactMessage $send,
        FormSignature $signature,
        SubmissionGuard $guard,
        LoggerInterface $logger,
        LoggerInterface $spamLogger,
    ): Response {
        $data = new ContactFormData();
        // Le jeton soumis détermine le nom du champ leurre attendu : la page
        // et le traitement restent d'accord même à cheval sur deux jours.
        $submitted = $request->request->all('contact');
        $token = \is_string($submitted['ts'] ?? null) ? $submitted['ts'] : $signature->issue();
        $form = $this->buildForm($data, $signature, $token);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $verdict = $guard->inspect(
                $token,
                (string) $form->get($signature->honeypotFieldFor($token))->getData(),
                $request->getClientIp() ?? 'inconnue',
                $data->name,
                $data->email,
                $data->message ?? '',
            );

            if (Decision::Retry === $verdict->decision) {
                // Onglet resté ouvert : ce n'est pas du spam, on le dit — et on
                // repart d'un jeton neuf pour que le renvoi aboutisse.
                $this->addFlash('error', "Cette page est restée ouverte un moment. Merci de renvoyer votre message — il n'a pas encore été transmis.");

                return $this->render(
                    'contact/index.html.twig',
                    ['form' => $this->buildForm($data, $signature, $signature->issue())],
                    new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY),
                );
            }

            if (Decision::Silence === $verdict->decision) {
                // Réponse indiscernable d'un envoi réussi : un rejet visible
                // apprendrait au bot à contourner le piège. Rien n'est envoyé.
                // Le message écarté est journalisé en entier : si un filtre se
                // trompait, rien ne serait perdu — tout est relisible ici.
                $spamLogger->warning('Message de contact écarté.', [
                    'motif' => $verdict->reason,
                    'ip' => $request->getClientIp(),
                    'nom' => $data->name,
                    'email' => $data->email,
                    'telephone' => $data->phone,
                    'message' => $data->message,
                ]);
                $this->addFlash('success', 'Merci ! Votre message a bien été envoyé.');

                return $this->redirectToRoute('contact');
            }

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

    private function buildForm(ContactFormData $data, FormSignature $signature, string $token): FormInterface
    {
        return $this->createForm(ContactType::class, $data, [
            'form_token' => $token,
            'honeypot_field' => $signature->honeypotFieldFor($token),
        ]);
    }
}
