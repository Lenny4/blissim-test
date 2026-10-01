<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CommentData;
use App\Form\CommentType;
use App\Model\Comment;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comments/{id}', requirements: ['id' => '\d+'])]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
    ) {
    }

    #[Route('/edit', name: 'comment_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $comment = $this->getComment($id);

        $form = $this->createForm(CommentType::class, CommentData::fromComment($comment));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CommentData $commentData */
            $commentData = $form->getData();
            $this->commentRepository->update($comment->getId(), (string) $commentData->getAuthor(), (string) $commentData->getContent());

            $this->addFlash('success', 'Le commentaire a été modifié.');

            return $this->redirectToProduct($comment);
        }

        return $this->render('comment/edit.html.twig', [
            'comment' => $comment,
            'form' => $form,
        ]);
    }

    /**
     * Suppression en POST uniquement, protégée par un jeton CSRF :
     * un simple lien (GET) pourrait être déclenché à l'insu de l'utilisateur.
     */
    #[Route('/delete', name: 'comment_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $comment = $this->getComment($id);

        if (!$this->isCsrfTokenValid('delete-comment-'.$comment->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToProduct($comment);
        }

        $this->commentRepository->delete($comment->getId());
        $this->addFlash('success', 'Le commentaire a été supprimé.');

        return $this->redirectToProduct($comment);
    }

    private function getComment(int $id): Comment
    {
        return $this->commentRepository->find($id) ?? throw $this->createNotFoundException(sprintf('Le commentaire #%d est introuvable.', $id));
    }

    private function redirectToProduct(Comment $comment): Response
    {
        return $this->redirectToRoute('product_show', ['id' => $comment->getProductId(), '_fragment' => 'comments']);
    }
}
