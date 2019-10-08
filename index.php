<?php

use App\Command\CopyCommand;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/vendor/autoload.php';

$dotenv = new Dotenv(false);

$envFile = __DIR__.'/.env';
if (!file_exists($envFile)) {
    throw new RuntimeException('.env file is not created');
}

$dotenv->load(__DIR__.'/.env');

$s3key = $_ENV['DO_SPACES_KEY'];
$s3secret = $_ENV['DO_SPACES_SECRET'];
$s3region = $_ENV['DO_SPACES_REGION'];
$s3bucket = $_ENV['DO_SPACES_BUCKETNAME'];

$sourceAdapter = new SftpAdapter(
    [
        'host' => $_ENV['SSH_HOST'],
        'port' => $_ENV['SSH_PORT'],
        'username' => $_ENV['SSH_USER'],
        'password' => 'password',
        'privateKey' => $_ENV['SSH_PATH_PRIVATEKEY'],
        'passphrase' => $_ENV['SSH_PATH_PRIVATEKEY_PASS'],
        'root' => $_ENV['SSH_FILE_SOURCE_DIR'],
    ]
);

//$configurator = (new SshShellConfigurator())
//    ->setRoot($_ENV['SSH_FILE_SOURCE_DIR'])
//    ->setUser($_ENV['SSH_USER'])
//    ->setHost($_ENV['SSH_HOST'])
//    ->setPrivateKey($_ENV['SSH_PATH_PRIVATEKEY'])
//    ->setPort($_ENV['SSH_PORT']);
//
//$sourceAdapter = (new SshShellFactory())->createAdapter($configurator);

$sourceFs = new Filesystem($sourceAdapter);

$targetClient = new S3Client(
    [
        'credentials' => [
            'key' => $_ENV['DO_SPACES_KEY'],
            'secret' => $_ENV['DO_SPACES_SECRET'],
        ],
        'region' => $_ENV['DO_SPACES_REGION'],
        'version' => 'latest',
        'endpoint' => 'https://'.$_ENV['DO_SPACES_REGION'].'.digitaloceanspaces.com',
    ]
);
$targetAdapter = new AwsS3Adapter($targetClient, $_ENV['DO_SPACES_BUCKETNAME']);
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
