<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

# This controller function is to view all user data at /api/backup


class BackupController extends AbstractController
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

    #[Route('/api/backup', name: 'app_backup', methods: ['GET'])]
    public function backup(): Response
    {
        $backupFile = '../var/backups/backup.sql';
        $backupDir = dirname($backupFile);

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        // Generate the SQL dump
        $command = sprintf('mysqldump --user=%s --password=%s --host=%s %s > %s',
            $_ENV['DATABASE_USER'],
            $_ENV['DATABASE_PASSWORD'],
            $_ENV['DATABASE_HOST'],
            $_ENV['DATABASE_NAME'],
            $backupFile
        );

        // Run the process
        $process = Process::fromShellCommandline($command . ' > ' . escapeshellarg($backupFile));

        // Execute the command
        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $this->logger->error('Failed to generate backup', [
                'error' => $exception->getMessage(),
                'output' => $process->getErrorOutput(),
            ]);
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Failed to generate backup: ' . $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    
        // Return the backup file as a response
        return new Response(file_get_contents($backupFile), 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="backup.sql"',
        ]);
      }
}