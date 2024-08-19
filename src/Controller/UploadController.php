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

# This controller function is to upload a csv file at api/upload
# The field name / KEY for the file is data, type is file
# The loaded file is .csv format where data is written
# The POST data from .csv file is uploaded into the database
# At the time of POST, an email is send to user to inform them about their data upload.

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

        // Remove duplicates using email as the unique key
        $users = array_values(array_reduce($users, function ($carry, $user) {
            $carry[$user['email']] = $user;
            return $carry;
        }, []));

        // Save data to the database
        foreach ($users as $user) {
            $userEntity = new User();
            $userEntity->setName($user['name']);
            $userEntity->setEmail($user['email']);
            $userEntity->setUsername($user['username']);
            $userEntity->setAddress($user['address']);
            $userEntity->setRole($user['role']);
            $this->entityManager->persist($userEntity);
        }

        $this->entityManager->flush();

        // Send emails in the background using a message queue or a separate process
        foreach ($users as $user) {
            $email = (new Email())
                ->from('hello@example.com')
                ->to($user['email'])
                ->subject('Data uploaded successfully!')
                ->text('I hope you are doing well. I wanted to Your data has been uploaded successfully to our database and you will be able to get weekly updates from us in the future !');

            $this->mailer->send($email);
        }

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