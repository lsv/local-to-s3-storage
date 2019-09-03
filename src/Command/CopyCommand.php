<?php

namespace App\Command;

use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CopyCommand extends Command
{
    /**
     * @var FilesystemInterface
     */
    private $source;

    /**
     * @var FilesystemInterface
     */
    private $target;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @inheritDoc
     */
    public function __construct(FilesystemInterface $source, FilesystemInterface $target)
    {
        parent::__construct();
        $this->source = $source;
        $this->target = $target;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this
            ->setName('local-to-s3-copy')
            ->setDescription('Copy files from local to a s3 aws storage (DO spaces included)')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Allow overwrite if file already exists on source')
            ->addOption('private', null, InputOption::VALUE_NONE, 'Set files as private on source');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;

        $this->readDir($output, '.');
    }

    private function readDir(OutputInterface $output, string $path): void
    {
        $output->writeln('Reading dir: "'.$path.'"');
        $files = $this->source->listContents($path);
        foreach ($files as $file) {
            switch ($file['type']) {
                case 'dir':
                    $this->readDir($output, $file['path']);
                    break;
                case 'file':
                    $this->copyFile($output, $file);
                    break;
            }
        }
    }

    private function copyFile(OutputInterface $output, array $filedata): void
    {
        $output->writeln('Handling file: "'.$filedata['path'].'"');
        try {
            $method = 'writeStream';
            if ($this->input->getOption('overwrite')) {
                $method = 'putStream';
            }

            $write = $this->target->$method(
                $filedata['path'],
                $this->source->readStream($filedata['path']),
                [
                    'visibility' => $this->input->getOption('private') ? 'private' : 'public'
                ]
            );
            if ($write) {
                $output->writeln('<info>'.$filedata['path'].' copied successfully</info>');
            } else {
                $output->writeln('<error>'.$filedata['path'].' not copied successfully</error>');
            }
        } catch (FileExistsException $e) {
            $output->writeln('<error>'.$filedata['path'].' already exists on target</error>');
        } catch (FileNotFoundException $e) {
            $output->writeln('<error>'.$filedata['path'].' does not exists on source</error>');
        }
    }

}
