<?php

namespace App\Home\Controller;

use App\Auth\Roles;
use App\Home\Entity\PublicDB;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Roles::ROLE_DATA_ADMIN)]
class PublicDBController extends AbstractController
{

    #[Route('/webadmin/public-database/{id}/edit', name: 'public_database_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        $repository = $entityManager->getRepository(PublicDB::class);
        $database = $repository->find($id);
    
        if (!$database) {
            throw $this->createNotFoundException('Database not found.');
        }
    
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit'.$database->getId(), $request->request->get('_token'))) {
                return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionA']);
            }

            $database->setUniprot($request->request->get('uniprot'));
            $database->setInterpro($request->request->get('interpro'));
            $database->setEna($request->request->get('ena'));
            $isActive = $request->request->has('active');
            $database->setActive($isActive);
    
            if ($isActive) {
                $activeEntries = $repository->findBy(['active' => true]);
                foreach ($activeEntries as $activedatabase) {
                    if ($activedatabase->getId() !== $database->getId()) {
                        $activedatabase->setActive(false);
                    }
                }
            }
    
            $entityManager->flush();
    
            return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionA']);
        }
    
        return $this->render('public_database/edit.html.twig', [
            'database' => $database,
        ]);
    }    

    #[Route('/webadmin/public-database/{id}/delete', name: 'public_database_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        $repository = $entityManager->getRepository(PublicDB::class);
        $database = $repository->find($id);

        if (!$database) {
            throw $this->createNotFoundException('Database not found.');
        }

        if ($this->isCsrfTokenValid('delete'.$database->getId(), $request->request->get('_token'))) {
            $entityManager->remove($database);
            $entityManager->flush();
        }

        return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionA']);
    }

    #[Route('/webadmin/public-database/create', name: 'public_database_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('create_public_database', $request->request->get('_token'))) {
            return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionA']);
        }

        $database = new PublicDB();
        $database->setUniprot($request->request->get('uniprot'));
        $database->setInterpro($request->request->get('interpro'));
        $database->setEna($request->request->get('ena'));
        $database->setActive($request->request->has('active'));

        if ($database->isActive()) {
            $repository = $entityManager->getRepository(PublicDB::class);
            $activeEntries = $repository->findBy(['active' => true]);
            foreach ($activeEntries as $activedatabase) {
                if ($activedatabase->getId() !== $database->getId()) {
                    $activedatabase->setActive(false);
                }
            }
        }

        $entityManager->persist($database);
        $entityManager->flush();

        return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionA']);
    }
}
