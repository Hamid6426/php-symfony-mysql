<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RestoreController extends AbstractController
{
    private $connection;
    private $logger;

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        Connection $connection,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    #[Route('/api/restore', name: 'app_restore', methods: ['POST'])]
    public function restore(Request $request): Response
    {
        // Case 1: File in var/backups/ directory
        $backupFile = $request->request->get('backup_file_name');
        if ($backupFile) {
            $backupFilePath = '../var/backups/' . $backupFile;

            if (!file_exists($backupFilePath)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Backup file does not exist.',
                ], Response::HTTP_NOT_FOUND);
            }
        } else {
            // Case 2: Uploaded file from the request
            /** @var UploadedFile $file */
            $file = $request->files->get('backup');

            if (!$file instanceof UploadedFile || !$file->isValid()) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid file upload.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $backupFilePath = $file->getRealPath(); // Get the real path of the uploaded file
        }

        // Restore the database using the correct process command format
        $command = [
            'mysql',
            '--user=' . $_ENV['DATABASE_USER'],
            '--password=' . $_ENV['DATABASE_PASSWORD'],
            '--host=' . $_ENV['DATABASE_HOST'],
            $_ENV['DATABASE_NAME'],
            '-e', 'source ' . $backupFilePath
        ];

        $process = new Process($command); // Pass the array directly
        $process->setTimeout(5000); // Set timeout if needed

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $this->logger->error('Failed to restore database', [
                'error' => $exception->getMessage(),
                'command' => implode(' ', $command),
                'output' => $process->getErrorOutput(),
            ]);

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Failed to restore database: ' . $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Database successfully restored.',
        ], Response::HTTP_OK);
    }
}
