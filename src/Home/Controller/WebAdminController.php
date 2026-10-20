<?php

namespace App\Home\Controller;

use App\Auth\Roles;
use App\Home\Entity\PublicDB;
use App\Home\Entity\PublicationsEntity;
use App\Home\Entity\PatentsEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[IsGranted(Roles::ROLE_DATA_ADMIN)]
class WebAdminController extends AbstractController
{
    #[Route('/webadmin', name: 'webadmin')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $public_databases_repository = $entityManager->getRepository(PublicDB::class);
        $databases = $public_databases_repository->findAll();

        $publications_repository = $entityManager->getRepository(PublicationsEntity::class);
        $publications = $publications_repository->findAll();

        $patents_repository = $entityManager->getRepository(PatentsEntity::class);
        $patents = $patents_repository->findAll();

        return $this->render('auth/webadmin/webadmin.html.twig', [
            'ruled_title' => 'Enzyme Function Initiative Tools',
            'databases' => $databases,
            'publications' => $publications,
            'patents' => $patents,
        ]);
    }

}
