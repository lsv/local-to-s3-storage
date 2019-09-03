<?php

use App\Command\CopyCommand;
use Aws\S3\S3Client;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$dotenv = new Dotenv(false);

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    throw new RuntimeException('.env file is not created');
}

$dotenv->load(__DIR__ . '/.env');

$localDirectory = __DIR__ . '/' . $_ENV['LOCAL_DIRECTORY'];
$s3key = $_ENV['DO_SPACES_KEY'];
$s3secret = $_ENV['DO_SPACES_SECRET'];
$s3region = $_ENV['DO_SPACES_REGION'];
$s3bucket = $_ENV['DO_SPACES_BUCKETNAME'];

$sourceAdapter = new Local($localDirectory);
$sourceFs = new Filesystem($sourceAdapter);

$targetClient = new S3Client([
    'credentials' => [
        'key'    => $s3key,
        'secret' => $s3secret,
    ],
    'region' => $s3region,
    'version' => 'latest',
    'endpoint' => 'https://' . $s3region . '.digitaloceanspaces.com',
]);
$targetAdapter = new AwsS3Adapter($targetClient, $s3bucket);
$targetFs = new Filesystem($targetAdapter);

$app = new Application;
$command = new CopyCommand($sourceFs, $targetFs);
$app->add($command);
$app->setDefaultCommand($command->getName(), true);

try {
    $app->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
