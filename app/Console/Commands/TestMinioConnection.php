<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Exception;

class TestMinioConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'minio:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MinIO S3 connection and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing MinIO S3 Connection...');
        $this->newLine();

        // Display current configuration
        $this->displayConfiguration();
        $this->newLine();

        // Test 1: Check if disk exists
        $this->testDiskExists();

        // Test 2: Test connection by listing files
        $this->testListFiles();

        // Test 3: Test write operation
        $this->testWriteFile();

        // Test 4: Test read operation
        $this->testReadFile();

        // Test 5: Test delete operation
        $this->testDeleteFile();

        $this->newLine();
        $this->info('✓ All tests completed!');
    }

    private function displayConfiguration()
    {
        $this->info('Current S3/MinIO Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Endpoint', config('filesystems.disks.s3.endpoint')],
                ['Bucket', config('filesystems.disks.s3.bucket')],
                ['Region', config('filesystems.disks.s3.region')],
                ['Access Key', substr(config('filesystems.disks.s3.key'), 0, 5) . '***'],
                ['Use Path Style', config('filesystems.disks.s3.use_path_style_endpoint') ? 'Yes' : 'No'],
            ]
        );
    }

    private function testDiskExists()
    {
        try {
            $disk = Storage::disk('s3');
            $this->info('✓ S3 disk configuration found');
        } catch (Exception $e) {
            $this->error('✗ S3 disk configuration error: ' . $e->getMessage());
        }
    }

    private function testListFiles()
    {
        try {
            $this->info('Testing: List files in bucket...');
            $files = Storage::disk('s3')->files();
            $this->info('✓ Successfully connected to MinIO');
            $this->info('  Found ' . count($files) . ' file(s) in bucket');

            if (count($files) > 0) {
                $this->info('  Sample files:');
                foreach (array_slice($files, 0, 5) as $file) {
                    $this->line('    - ' . $file);
                }
            }
        } catch (Exception $e) {
            $this->error('✗ Failed to list files: ' . $e->getMessage());
            $this->warn('  This might indicate connection or bucket access issues');
        }
    }

    private function testWriteFile()
    {
        try {
            $this->info('Testing: Write file to MinIO...');
            $testContent = 'MinIO connection test - ' . now()->toDateTimeString();
            $testPath = 'test/connection-test.txt';

            $result = Storage::disk('s3')->put($testPath, $testContent);

            if ($result) {
                $this->info('✓ Successfully wrote test file to MinIO');
                $this->info('  Path: ' . $testPath);
            } else {
                $this->error('✗ Failed to write test file');
            }
        } catch (Exception $e) {
            $this->error('✗ Write operation failed: ' . $e->getMessage());
        }
    }

    private function testReadFile()
    {
        try {
            $this->info('Testing: Read file from MinIO...');
            $testPath = 'test/connection-test.txt';

            if (Storage::disk('s3')->exists($testPath)) {
                $content = Storage::disk('s3')->get($testPath);
                $this->info('✓ Successfully read test file from MinIO');
                $this->info('  Content: ' . substr($content, 0, 50) . '...');
            } else {
                $this->warn('⚠ Test file does not exist (write test may have failed)');
            }
        } catch (Exception $e) {
            $this->error('✗ Read operation failed: ' . $e->getMessage());
        }
    }

    private function testDeleteFile()
    {
        try {
            $this->info('Testing: Delete file from MinIO...');
            $testPath = 'test/connection-test.txt';

            if (Storage::disk('s3')->exists($testPath)) {
                $result = Storage::disk('s3')->delete($testPath);

                if ($result) {
                    $this->info('✓ Successfully deleted test file from MinIO');
                } else {
                    $this->error('✗ Failed to delete test file');
                }
            } else {
                $this->warn('⚠ Test file does not exist, skipping delete test');
            }
        } catch (Exception $e) {
            $this->error('✗ Delete operation failed: ' . $e->getMessage());
        }
    }
}
