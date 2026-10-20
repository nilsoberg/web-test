<?php

namespace App\Home\Controller;

use App\Attribute\RateLimit;
use App\Form\FeedbackForm;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

class FeedbackController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private string $fromEmail,
        private string $fromName,
        private string $feedbackEmail,
    )
    {
    }

    #[Route('/feedback', name: 'feedback')]
    #[RateLimit(limiter: 'contact_form')]
    public function feedback(Request $request): Response
    {
        $form = $this->createForm(FeedbackForm::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $honeypot = $form->get('website_url')->getData();
            $renderTimeData = (int) $form->get('form_render_time')->getData();

            // Trap if the honeypot is filled, or the timestamp is missing, or the time delta is
            // under 2 seconds, or the timestamp is in the future
            if (!empty($honeypot)
                || $renderTimeData === null
                || (time() - (int)$renderTimeData) < 2
                || (int)$renderTimeData > time())
            {
                // Fake a success response so the bot doesn't retry with new tactics
                $this->addFlash('success', 'Your message has been sent...');
                return $this->redirectToRoute('home');
            }

            $feedbackData = $form->getData();

            $this->sendFeedback($feedbackData);

            $this->addFlash('success', 'Thank you for your feedback. We will respond shortly.');

            return $this->redirectToRoute('home');
        }

        // Render the form template
        return $this->render('feedback/feedback.html.twig', [
            'feedbackForm' => $form->createView(),
        ]);
    }

    private function sendFeedback($data): void
    {
        $feedbackAddressess = array_map(
            fn(string $email) => new Address(trim($email)),
            explode(',', $this->feedbackEmail)
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Site Feedback'))
            ->to(...$feedbackAddressess)
            ->replyTo(new Address($data['email'], $data['name']))
            ->subject('EFI Tools Feedback Received')
            ->htmlTemplate('email/feedback.html.twig')
            ->textTemplate('email/feedback.txt.twig')
            ->context([
                'sender_name' => $data['name'],
                'sender_email' => $data['email'],
                'message_body' => $data['message'],
                'organization' => $data['organization'] ?? 'N/A',
                'submitted_at' => new \DateTimeImmutable(),
            ]);

        $this->mailer->send($email);
    }
}

