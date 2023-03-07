<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\Routing\Annotation\Route;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class ApiClientController extends AbstractController
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    #[Route('/external', name: 'external_api', methods: 'GET')]
    public function getSymfonyDoc(JWTTokenManagerInterface $JWTManager): JsonResponse
    {
        // $user = new User();
        // $user->setEmail('admin@mail.com');
        // $user->setPassword('password');

        // $token = $JWTManager->create($user);

        // $response = $this->httpClient->request(
        //     'GET',
        //     'http://localhost:5000/api/books',
        //     [],
        //     [],
        //     [
        //         'Accept' => 'application/json',
        //         'Authorization' => $token
        //     ]
        // );

        $response = $this->httpClient->request(
             'GET',
             'http://geo.api.gouv.fr/departements/95');


        return new JsonResponse($response->getContent(), $response->getStatusCode(), [], true);
    }
}
