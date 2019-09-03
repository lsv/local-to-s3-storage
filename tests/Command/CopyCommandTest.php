<?php

use App\Command\CopyCommand;
use League\Flysystem\Adapter\Local;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Memory\MemoryAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CopyCommandTest extends TestCase
{
    /**
     * @var Filesystem
     */
    private $sourceFs;

    /**
     * @var Filesystem
     */
    private $targetFs;

    /**
     * @test
     */
    public function can_read_directories(): void
    {
        $command = new CopyCommand($this->sourceFs, $this->targetFs);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $output = $tester->getDisplay();
        $lines = array_filter(array_map('trim', explode("\n", $output)));
        $lines = array_filter($lines, static function ($line) {
            return strpos($line, 'Reading dir') === 0;
        });
        $this->assertCount(6, $lines);
    }

    /**
     * @test
     */
    public function will_write_error_if_file_not_exists(): void
    {
        $sourceFs = $this->createMock(FilesystemInterface::class);
        $sourceFs->method('listContents')->willReturn([
            [
                'type' => 'file',
                'path' => 'doesnotexistsfile.jpg',
                'timestamp' => time(),
                'dirname' => '',
                'basename' => 'doesnotexistsfile.jpg',
                'extension' => 'jpg',
                'filename' => 'doesnotexistsfile',
            ]
        ]);
        $sourceFs->method('readStream')->willThrowException(new FileNotFoundException('doesnotexistsfile.jpg'));

        $command = new CopyCommand($sourceFs, $this->targetFs);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $output = $tester->getDisplay();
        $lines = array_filter(array_map('trim', explode("\n", $output)));
        $this->assertContains('doesnotexistsfile.jpg does not exists on source', $lines);
    }

    /**
     * @test
     */
    public function will_write_error_if_file_aready_exists_on_target(): void
    {
        $targetFs = $this->createMock(FilesystemInterface::class);
        $targetFs->method('writeStream')->willThrowException(new FileExistsException(''));

        $command = new CopyCommand($this->sourceFs, $targetFs);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $output = $tester->getDisplay();
        $lines = array_filter(array_map('trim', explode("\n", $output)));
        $this->assertContains('dir/dir1/dir2/alphafile1.txt already exists on target', $lines);
    }

    /**
     * @test
     */
    public function will_overwrite_file_if_already_exists(): void
    {
        $target = new Local(__DIR__ . '/../data');
        $targetFs = new Filesystem($target);

        $command = new CopyCommand($this->sourceFs, $targetFs);
        $tester = new CommandTester($command);
        $tester->execute([
            '--overwrite' => true,
        ]);
        $output = $tester->getDisplay();
        $lines = array_filter(array_map('trim', explode("\n", $output)));
        $this->assertNotContains('dir/dir1/dir2/alphafile1.txt already exists on target', $lines);
    }

    /**
     * @test
     */
    public function will_make_file_public(): void
    {
        $command = new CopyCommand($this->sourceFs, $this->targetFs);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->targetFs->assertPresent('file1.txt');
        $this->assertSame('public', $this->targetFs->getVisibility('file1.txt'));
    }

    /**
     * @test
     */
    public function will_make_file_private(): void
    {
        $command = new CopyCommand($this->sourceFs, $this->targetFs);
        $tester = new CommandTester($command);
        $tester->execute([
            '--private' => true,
        ]);
        $this->targetFs->assertPresent('file1.txt');
        $this->assertSame('private', $this->targetFs->getVisibility('file1.txt'));
    }

    /**
     * @test
     */
    public function will_copy_files(): void
    {
        $this->expectNotToPerformAssertions();

        $command = new CopyCommand($this->sourceFs, $this->targetFs);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->targetFs->assertPresent('file1.txt');
        $this->targetFs->assertPresent('dir/dir1/file2.txt');
        $this->targetFs->assertPresent('dir/dir1/dir21/file21.txt');
        $this->targetFs->assertPresent('dir/dir1/dir2/alphafile1.txt');
        $this->targetFs->assertPresent('dir/dir1/dir2/dir3/file3.txt');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $source = new Local(__DIR__ . '/../data');
        $this->sourceFs = new Filesystem($source);

        $target = new MemoryAdapter();
        $this->targetFs = new Filesystem($target);
    }

}
