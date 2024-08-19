<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

# This controller function is to view all user data at /api/users
class UserController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    #[Route('/api/users', name: 'app_users', methods: ['GET'])]
    public function getUsers(): JsonResponse
    {
        $users = $this->entityManager
        ->getRepository(User::class)
        ->findAll();

        $userData = [];
        
        foreach ($users as $user) {
            $userData[] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'address' => $user->getAddress(),
                'role' => $user->getRole(),
                'createdAt' => $user->getCreatedAt()->format('c'),
                'updatedAt' => $user->getUpdatedAt()->format('c'),
            ];
        }

        return new JsonResponse(['status' => 'success', 'data' => $userData]);
    }
}