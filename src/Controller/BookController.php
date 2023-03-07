<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use App\Service\VersioningService;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
// use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
// use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use OpenApi\Annotations as OA;


/**
 * Nelmio / Cette méthode permet de récupérer l'ensemble des livres.
 *
 * @OA\Response(
 *     response=200,
 *     description="Retourne la liste des livres",
 *     @OA\JsonContent(
 *        type="array",
 *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
 *     )
 * )
 * @OA\Parameter(
 *     name="page",
 *     in="query",
 *     description="La page que l'on veut récupérer",
 *     @OA\Schema(type="int")
 * )
 *
 * @OA\Parameter(
 *     name="limit",
 *     in="query",
 *     description="Le nombre d'éléments que l'on veut récupérer",
 *     @OA\Schema(type="int")
 * )
 * @OA\Tag(name="Books")
 *
 */

class BookController extends AbstractController
{
    private BookRepository $bookRepository;
    private SerializerInterface $serializer;

    public function __construct(BookRepository $bookRepository, SerializerInterface $serializer)
    {
        $this->bookRepository = $bookRepository;
        $this->serializer = $serializer;
    }

    #[Route('/api/books', name: 'getBooks', methods: ['GET'])]
    public function getAllBooks(Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // $books = $this->bookRepository->findAll();

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $jsonBooks = $cachePool->get($idCache, function (ItemInterface $item) use ($page, $limit) {
            // echo "DONNEES PAS ENCORE EN CACHE";
            $item->tag("booksCache");
            $item->expiresAfter(60);
            $books = $this->bookRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getBooks']);
            // return $this->serializer->serialize($books, 'json', ['groups' => 'getBooks']);
            return $this->serializer->serialize($books, 'json', $context);
        });

        // $jsonBooks = $this->serializer->serialize($books, 'json', ['groups' => 'getBooks']);

        return new JsonResponse($jsonBooks, Response::HTTP_OK, [], true);
    }


    #[Route('/api/books/{id}', name: 'getBook', methods: ['GET'])]
    public function getBooks(Book $book, VersioningService $versioningService): JsonResponse
    {

        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $context->setVersion($version);

        // $context = SerializationContext::create()->setGroups(['getBooks']);
        // $context->setVersion("2.0");

        $jsonBook = $this->serializer->serialize($book, 'json', $context);

        // $jsonBook = $this->serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        if ($jsonBook) {
            return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
        }

        new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }


    #[Route('/api/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createBook(
        Request $request,
        UrlGeneratorInterface $urlGenerator,
        AuthorRepository $authorRepository,
        ValidatorInterface $validator
    ): JsonResponse {

        $book = $this->serializer->deserialize($request->getContent(), Book::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($book);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $this->serializer->serialize($errors[0]->getMessage(), 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
            // throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }

        $json = json_decode($request->getContent());
        if (isset($json->idAuthor)) {
            $book->setAuthor($authorRepository->findOneBy(['id' => $json->idAuthor]));
        }

        $this->bookRepository->save($book, true);

        $context = SerializationContext::create()->setGroups(['getBooks']);

        // $jsonBook = $this->serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        $jsonBook = $this->serializer->serialize($book, 'json', $context);

        $location = $urlGenerator->generate('getBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['location' => $location], true);
    }

    #[Route('/api/books/{id}', name: 'updateBook', methods: ['PUT'])]
    public function updateBook(
        Book $currentBook,
        Request $request,
        AuthorRepository $authorRepository
    ): JsonResponse {

        if (empty($currentBook)) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        // $updateBook = $this->serializer->deserialize(
        //     $request->getContent(),
        //     Book::class,
        //     'json',
        //     [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]
        // );

        $newBook = $this->serializer->deserialize(
            $request->getContent(),
            Book::class,
            'json'
        );

        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        $json = json_decode($request->getContent());

        if (isset($json->idAuthor)) {
            $currentBook->setAuthor($authorRepository->findOneBy(['id' => $json->idAuthor]));
        }

        $this->bookRepository->save($currentBook, true);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }



    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(Book $book, TagAwareCacheInterface $cachePool): JsonResponse
    {

        $cachePool->invalidateTags(["booksCache"]);

        $this->bookRepository->remove($book, true);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
