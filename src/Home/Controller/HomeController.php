<?php

namespace App\Home\Controller;

use App\Attribute\RateLimit;
use App\Repository\SiteNoticeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this -> render('home/home.html.twig', [
            'ruled_title' => 'Enzyme Function Initiative Tools'
        ]);
    }

    public function render_global_notice(
        SiteNoticeRepository $repository,
    ): Response
    {
        $activeNotice = $repository->findActiveNotice();

        return $this->render('_partials/global_notice.html.twig', [
            'notice' => $activeNotice
        ]);
    }
}
