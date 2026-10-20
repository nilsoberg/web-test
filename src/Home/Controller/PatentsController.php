<?php

namespace App\Home\Controller;

use App\Auth\Roles;
use App\Home\Entity\PatentsEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Roles::ROLE_DATA_ADMIN)]
class PatentsController extends AbstractController
{

    #[Route('/webadmin/patents/{id}/edit', name: 'patents_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        $patents_repository = $entityManager->getRepository(PatentsEntity::class);
        $patent = $patents_repository->find($id);
    
        if (!$patent) {
            throw $this->createNotFoundException('Patent not found.');
        }
    
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit'.$patent->getId(), $request->request->get('_token'))) {
                return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionC']);
            }

            $patent->setNumber($request->request->get('number'));
            $patent->setTitle($request->request->get('title'));
            $patent->setAuthor($request->request->get('author'));
            $patent->setYear($request->request->get('year'));
            $patent->setMonth($request->request->get('month'));
            $patent->setDay($request->request->get('day'));
    
            $entityManager->flush();
    
            return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionC']);
        }
    
        return $this->render('public_database/edit.html.twig', [
            'patent' => $patent,
        ]);
    }    

    #[Route('/webadmin/patents/{id}/delete', name: 'patents_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        $patents_repository = $entityManager->getRepository(PatentsEntity::class);
        $patent = $patents_repository->find($id);

        if (!$patent) {
            throw $this->createNotFoundException('Patent not found.');
        }

        if ($this->isCsrfTokenValid('delete'.$patent->getId(), $request->request->get('_token'))) {
            $entityManager->remove($patent);
            $entityManager->flush();
        }

        return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionC']);
    }

    #[Route('/webadmin/patents/create', name: 'patents_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('create_patent', $request->request->get('_token'))) {
            return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionC']);
        }

        $patent = new PatentsEntity();

        $patent->setNumber($request->request->get('number'));
        $patent->setTitle($request->request->get('title'));
        $patent->setAuthor($request->request->get('author'));
        $patent->setYear($request->request->get('year'));
        $patent->setMonth($request->request->get('month'));
        $patent->setDay($request->request->get('day'));
        

        $entityManager->persist($patent);
        $entityManager->flush();

        return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionC']);
    }
}
