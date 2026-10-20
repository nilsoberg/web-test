<?php

namespace App\Home\Controller;

use App\Home\Repository\PublicationsRepository;
use App\Home\Repository\PatentsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TrainingController extends AbstractController
{
    private $publicationsRepository;
    private $patentsRepository;

    public function __construct(PublicationsRepository $publicationsRepository, PatentsRepository $patentsRepository)
    {
        $this->publicationsRepository = $publicationsRepository;
        $this->patentsRepository = $patentsRepository;
    }

    #[Route('/training', name: 'training')]
    public function index(): Response
    {
        $publications = $this->publicationsRepository->findAll();
        $publicationsCount = count($publications);

        $patents = $this->patentsRepository->findAll();
        $patentsCount = count($patents);

        // todo: testing Hard Coded Jobs

        $greJobs = [
            [
                'id' => 101,
                'name' => 'GRE Job 1',
                'date_completed' => new \DateTime('2023-08-15'),
                'status' => 'Completed',
            ],
            [
                'id' => 102,
                'name' => 'GRE Job 2',
                'date_completed' => new \DateTime('2023-09-10'),
                'status' => 'Running',
            ],
        ];

        return $this->render('home/training/training.html.twig', [
            'ruled_title' => 'EFI - Training Resources:',
            'publicationsCount' => $publicationsCount,
            'publications' => $publications,
            'patentsCount' => $patentsCount,
            'patents' => $patents,
        ]);
    }
}
