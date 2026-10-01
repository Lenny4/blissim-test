<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CommentData;
use App\Exception\ProductNotFoundException;
use App\Form\CommentType;
use App\Model\Product;
use App\Repository\CommentRepository;
use App\Repository\ProductRepository;
use App\Service\Provider\ProductProviderStrategyInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductProviderStrategyInterface $productProviderStrategy,
        private readonly ProductRepository $productRepository,
        private readonly CommentRepository $commentRepository,
    ) {
    }

    /**
     * Le catalogue est lu dans l'API, puis enregistré en base (upsert) : chaque produit
     * reçoit ainsi l'identifiant de l'application utilisé par le lien vers sa fiche.
     */
    #[Route('/', name: 'product_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $this->productRepository->synchronize($this->productProviderStrategy->findAll()),
        ]);
    }

    /**
     * Fiche produit + liste des commentaires + formulaire d'ajout (soumis sur la même URL).
     * L'URL contient l'identifiant de l'application (product.id), celui que référencent les commentaires.
     */
    #[Route('/products/{id}', name: 'product_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request): Response
    {
        $product = $this->getProduct($id);

        $form = $this->createForm(CommentType::class, new CommentData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CommentData $commentData */
            $commentData = $form->getData();
            $this->commentRepository->create($id, (string) $commentData->getAuthor(), (string) $commentData->getContent());

            $this->addFlash('success', 'Merci, votre commentaire a été publié.');

            // Post/Redirect/Get : évite une double soumission en cas de rafraîchissement
            return $this->redirectToRoute('product_show', ['id' => $id, '_fragment' => 'comments']);
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'comments' => $this->commentRepository->findByProduct($id),
            'form' => $form,
        ]);
    }

    /**
     * Le produit est retrouvé en base par son id. S'il provient d'une source externe,
     * on affiche sa version à jour, lue dans l'API (qui reste la source de vérité) et réenregistrée.
     */
    private function getProduct(int $id): Product
    {
        $product = $this->productRepository->find($id)
            ?? throw $this->createNotFoundException(sprintf('Le produit #%d est introuvable.', $id));

        if (null === $product->getSupplierProductId()) {
            return $product;
        }

        try {
            return $this->productRepository->save($this->productProviderStrategy->find($product->getSupplierProductId()));
        } catch (ProductNotFoundException $e) {
            throw $this->createNotFoundException($e->getMessage(), $e);
        }
    }
}
