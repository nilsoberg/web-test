<?php

namespace App\Auth\Controller;

use App\Entity\SiteNotice;
use App\Form\SiteNoticeForm;
use App\Repository\SiteNoticeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notices')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class SiteNoticeController extends AbstractController
{
    #[Route('/', name: 'app_admin_notice_index', methods: ['GET', 'POST'])]
    public function index(
        EntityManagerInterface $em,
        Request $request, 
        SiteNoticeRepository $repository, 
    ): Response {
        $notice = new SiteNotice();
        $form = $this->createForm(SiteNoticeForm::class, $notice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($notice->isActive()) {
                // Deactivate any currently active notices to maintain a single source of truth
                $currentActive = $repository->findBy(['isActive' => true]);
                foreach ($currentActive as $oldNotice) {
                    $oldNotice->setIsActive(false);
                }
                $notice->setActivatedAt(new \DateTimeImmutable());
            }

            $em->persist($notice);
            $em->flush();

            $this->addFlash('success', 'Site notice configuration updated successfully.');
            return $this->redirectToRoute('app_admin_notice_index');
        }

        // Get history ordered by newest records first
        $history = $repository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('auth/site_notice/index.html.twig', [
            'form' => $form->createView(),
            'history' => $history,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_notice_toggle', methods: ['POST'])]
    public function toggleActive(
        EntityManagerInterface $em,
        SiteNotice $notice, 
        SiteNoticeRepository $repository, 
    ): Response {
        $currentStatus = $notice->isActive();
        
        if (!$currentStatus) {
            // Activating this banner: shut off all others
            $activeNotices = $repository->findBy(['isActive' => true]);
            foreach ($activeNotices as $oldNotice) {
                $oldNotice->setIsActive(false);
            }
            $notice->setIsActive(true);
            $notice->setActivatedAt(new \DateTimeImmutable());
        } else {
            // Simply deactivating the notice
            $notice->setIsActive(false);
        }

        $em->flush();
        $this->addFlash('success', 'Banner display status toggled.');
        return $this->redirectToRoute('app_admin_notice_index');
    }
}
