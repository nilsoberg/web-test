<?php

namespace App\Home\Controller;

use App\Auth\Roles;
use App\Home\Entity\PublicationsEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Roles::ROLE_DATA_ADMIN)]
class PublicationsController extends AbstractController
{

    #[Route('/webadmin/publications/{id}/edit', name: 'publications_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        $publications_repository = $entityManager->getRepository(PublicationsEntity::class);
        $publication = $publications_repository->find($id);
    
        if (!$publication) {
            throw $this->createNotFoundException('Publication not found.');
        }
    
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit'.$publication->getId(), $request->request->get('_token'))) {
                return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionB']);
            }

            $publication->setAuthor($request->request->get('author'));
            $publication->setTitle($request->request->get('title'));
            $publication->setSecondaryTitle($request->request->get('secondaryTitle'));
            $publication->setPublisher($request->request->get('publisher'));
            $publication->setYear($request->request->get('year'));
            $publication->setEdition($request->request->get('edition'));
            $publication->setNumber($request->request->get('number'));
            $publication->setPages($request->request->get('pages'));
            $publication->setUrl($request->request->get('url'));
    
            $entityManager->flush();
    
            return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionB']);
        }
    
        return $this->render('public_database/edit.html.twig', [
            'publication' => $publication,
        ]);
    }    

    #[Route('/webadmin/publications/{id}/delete', name: 'publications_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        $publications_repository = $entityManager->getRepository(PublicationsEntity::class);
        $publication = $publications_repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication not found.');
        }

        if ($this->isCsrfTokenValid('delete'.$publication->getId(), $request->request->get('_token'))) {
            $entityManager->remove($publication);
            $entityManager->flush();
        }

        return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionB']);
    }

    #[Route('/webadmin/publications/create', name: 'publications_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('create_publication', $request->request->get('_token'))) {
            return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionB']);
        }

        $publication = new PublicationsEntity();

        $publication->setAuthor($request->request->get('author'));
        $publication->setTitle($request->request->get('title'));
        $publication->setSecondaryTitle($request->request->get('secondaryTitle'));
        $publication->setPublisher($request->request->get('publisher'));
        $publication->setYear($request->request->get('year'));
        $publication->setEdition($request->request->get('edition'));
        $publication->setNumber($request->request->get('number'));
        $publication->setPages($request->request->get('pages'));
        $publication->setUrl($request->request->get('url'));

        $entityManager->persist($publication);
        $entityManager->flush();

        return $this->redirectToRoute('webadmin', ['_fragment' => 'WAoptionB']);
    }
}
