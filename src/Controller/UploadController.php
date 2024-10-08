<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class UploadController extends AbstractController
{
    private $entityManager;
    private $mailer;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
    }

    #[Route('/api/upload', name: 'app_upload')]
public function uploadData(Request $request): JsonResponse
{
    try {
        $file = $request->files->get('data');

        if (!$file instanceof UploadedFile) {
            throw new JsonException('No data.csv file uploaded or POST');
        }

        $users = [];

        // Open the CSV file for reading
        $handle = fopen($file->getPathname(), 'r');

        // skip header row
        fgetcsv($handle, 1000, ",");

        // Read CSV data and store it in an array
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Basic CSV data validation
            if (count($data) !== 5) {
                throw new JsonException('Invalid CSV data');
            }

            $users[] = [
                'name' => $data[0],
                'email' => $data[1],
                'username' => $data[2],
                'address' => $data[3],
                'role' => $data[4],
            ];
        }

        fclose($handle);

       // Save data to the database
foreach ($users as $user) {
    // Check if a user with the same email already exists
    $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $user['email']]);

    if (!$existingUser) {
        // If no existing user is found, create a new one
        $userEntity = new User();
        $userEntity->setName($user['name']);
        $userEntity->setEmail($user['email']);
        $userEntity->setUsername($user['username']);
        $userEntity->setAddress($user['address']);
        $userEntity->setRole($user['role']);
        $this->entityManager->persist($userEntity);

        // Send an email to the user
        $email = (new Email())
            ->from('hello@example.com')
            ->to($user['email'])
            ->subject('Data uploaded successfully!')
            ->text('Your data has been uploaded successfully to our database!');

        $this->mailer->send($email);
    } else {
        // If an existing user is found, update their information
        $existingUser->setName($user['name']);
        $existingUser->setUsername($user['username']);
        $existingUser->setAddress($user['address']);
        $existingUser->setRole($user['role']);
    }
}

$this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => 'Data uploaded successfully!',
            'data' => [],
        ]);
        } catch (JsonException $e) {
        return $this->json([
            'status' => 'error because this address process POST request but it mean that the site is workking',
            'message' => $e->getMessage(),
            'data' => [],
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}

# This controller function is to upload a csv file at api/upload
# The field name / KEY for the file is data, type is file
# The loaded file is .csv format where data is written
# The POST data from .csv file is uploaded into the database
# At the time of POST, an email is send to user to inform them about their data upload.
# Coded by Hamid with symfony docs, GPT and meta.
