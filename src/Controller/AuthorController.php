<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AuthorController extends AbstractController
{
    private AuthorRepository $authorRepository;
    private SerializerInterface $serializer;

    public function __construct(AuthorRepository $authorRepository, SerializerInterface $serializer)
    {
        $this->authorRepository = $authorRepository;
        $this->serializer = $serializer;
    }

    #[Route('/api/authors', name: 'getAuthors', methods: ['GET'])]
    public function getAllAuthors(Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // $authors = $this->authorRepository->findAll();

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllAuthors-" . $page . "-" . $limit;
    
        $jsonAuthors = $cachePool->get($idCache, function (ItemInterface $item) use ($page, $limit) {
            echo "DONNEES PAS ENCORE EN CACHE";
            $item->tag("authorsCache");
            $item->expiresAfter(60);
            $books = $this->authorRepository->findAllWithPagination($page, $limit);
            return $this->serializer->serialize($books, 'json', ['groups' => 'getBooks']);
        });

        // $jsonAuthors = $this->serializer->serialize($authors, 'json', ['groups' => 'getBooks']);

        return new JsonResponse($jsonAuthors, Response::HTTP_OK, [], true);
    }


    #[Route('/api/authors/{id}', name: 'getAuthor', methods: ['GET'])]
    public function getauthors(Author $author): JsonResponse
    {
        // $author = $this->authorRepository->findOneBy(['id' => $id]);

        $jsonAuthor = $this->serializer->serialize($author, 'json', ['groups' => 'getBooks']);

        if ($jsonAuthor) {
            return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
        }

        new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/authors', name: 'createAuthor', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createAuthor(
        Request $request,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator
    ): JsonResponse {

        $author = $this->serializer->deserialize($request->getContent(), Author::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($author);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $this->serializer->serialize($errors[0]->getMessage(), 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
            // throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }

        $this->authorRepository->save($author, true);

        $jsonAuthor = $this->serializer->serialize($author, 'json', ['groups' => 'getBooks']);

        $location = $urlGenerator->generate('getAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ['location' => $location], true);
    }


    #[Route('/api/authors/{id}', name: 'updateAuthor', methods: ['PUT'])]
    public function updateAuthor(
        Author $currentAuthor,
        Request $request,
    ): JsonResponse {

        // $currentAuthor = $this->authorRepository->findOneBy(['id' => $id]);

        if (empty($currentAuthor)) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $updateAuthor = $this->serializer->deserialize(
            $request->getContent(),
            Author::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]
        );

        $this->authorRepository->save($updateAuthor, true);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    public function deleteBook(Author $author, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["authorsCache"]);

        $this->authorRepository->remove($author, true);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
