<?php
namespace Burgomaster;

/**
 * Packages the zip and phar file using a staging directory.
 *
 * @license MIT, Michael Dowling https://github.com/mtdowling
 * @license https://github.com/mtdowling/Burgomaster/LICENSE
 */
class Packager
{
    /** @var string Base staging directory of the project */
    public $stageDir;

    /** @var string Root directory of the project */
    public $projectRoot;

    /** @var array stack of sections */
    private $sections = [];

    /**
     * @param string $stageDir    Staging base directory
     * @param string $projectRoot Root directory of the project
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __construct($stageDir, $projectRoot = null)
    {
        $this->startSection('setting_up');
        $this->stageDir = $stageDir;
        $this->projectRoot = $projectRoot;

        if (!$this->stageDir || $this->stageDir == '/') {
            throw new \InvalidArgumentException('Invalid base directory');
        }

        if (is_dir($this->stageDir)) {
            $this->debug("Removing existing directory: $this->stageDir");
            echo $this->exec("rm -rf $this->stageDir");
        }

        $this->debug("Creating staging directory: $this->stageDir");

        if (!mkdir($this->stageDir)) {
            throw new \RuntimeException("Could not create {$this->stageDir}");
        }

        $this->stageDir = realpath($this->stageDir);
        $this->debug("Creating staging directory at: {$this->stageDir}");

        if (!is_dir($this->projectRoot)) {
            throw new \InvalidArgumentException(
                "Project root not found: $this->projectRoot"
            );
        }

        $this->endSection();
        $this->startSection('staging');

        chdir($this->projectRoot);
    }

    /**
     * Cleanup if the last section was not already closed.
     */
    public function __destruct()
    {
        if ($this->sections) {
            $this->endSection();
        }
    }

    /**
     * Call this method when starting a specific section of the packager.
     *
     * @param string $section Part of the packager that is running
     */
    public function startSection($section)
    {
        $this->sections[] = $section;
        $this->debug('Starting');
    }

    /**
     * Call this method when leaving the last pushed section of the packager.
     */
    public function endSection()
    {
        if ($this->sections) {
            $this->debug('Completed');
            array_pop($this->sections);
        }
    }

    /**
     * Prints a debug message to STDERR
     *
     * @param string $message Message to echo to STDERR
     */
    public function debug($message)
    {
        $prefix = date('c') . ': ';

        if ($this->sections) {
            $prefix .= '[' . end($this->sections) . '] ';
        }

        fwrite(STDERR, $prefix . $message . "\n");
    }

    /**
     * Copies a file and creates the destination directory if needed.
     *
     * @param string $from File to copy
     * @param string $to   Destination to copy the file to, relative to the
     *                     base staging directory.
     * @throws \InvalidArgumentException if the file cannot be found
     * @throws \RuntimeException if the directory cannot be created.
     * @throws \RuntimeException if the file cannot be copied.
     */
    public function deepCopy($from, $to)
    {
        if (!is_file($from)) {
            throw new \InvalidArgumentException("File not found: {$from}");
        }

        $to = str_replace('//', '/', $this->stageDir . '/' . $to);
        $dir = dirname($to);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new \RuntimeException("Unable to create directory: $dir");
            }
        }

        if (!copy($from, $to)) {
            throw new \RuntimeException("Unable to copy $from to $to");
        }
    }

    /**
     * Recursively copy one folder to another.
     *
     * Any LICENSE file is automatically copied.
     *
     * @param string $sourceDir  Source directory to copy from
     * @param string $destDir    Directory to copy the files to that is relative
     *                           to the the stage base directory.
     * @param array  $extensions File extensions to copy from the $sourceDir.
     *                           Defaults to "php" files only (e.g., ['php']).
     * @throws \InvalidArgumentException if the source directory is invalid.
     */
    function recursiveCopy(
        $sourceDir,
        $destDir,
        $extensions = ['php', 'pem']
    ) {
        if (!realpath($sourceDir)) {
            throw new \InvalidArgumentException("$sourceDir not found");
        }

        $sourceDir = realpath($sourceDir);
        $exts = array_fill_keys($extensions, true);
        $iter = new \RecursiveDirectoryIterator($sourceDir);
        $iter = new \RecursiveIteratorIterator($iter);
        $total = 0;

        $this->startSection('copy');
        $this->debug("Starting to copy files from $sourceDir");

        foreach ($iter as $file) {
            if (isset($exts[$file->getExtension()])
                || $file->getBaseName() == 'LICENSE'
            ) {
                // Remove the source directory from the destination path
                $toPath = str_replace($sourceDir, '', (string) $file);
                $toPath = $destDir . '/' . $toPath;
                $toPath = str_replace('//', '/', $toPath);
                $this->deepCopy((string) $file, $toPath);
                $total++;
            }
        }

        $this->debug("Copied $total files from $sourceDir");
        $this->endSection();
    }

    /**
     * Execute a command and throw an exception if the return code is not 0
     *
     * @param string $command Command to execute
     *
     * @return string Returns the output as a string
     * @throws \RuntimeException on error.
     */
    public function exec($command)
    {
        $this->debug("Executing: $command");
        $output = $returnValue = null;
        exec($command, $output, $returnValue);

        if ($returnValue != 0) {
            throw new \RuntimeException('Error executing command: '
                . $command . ' : ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    /**
     * Creates a class-map autoloader to the staging directory in a file
     * named autoloader.php
     *
     * @param array $files Files to explicitly require in the autoloader
     * @throws \RuntimeException if the file cannot be written
     */
    function createAutoloader($files = [])
    {
        $sourceDir = realpath($this->stageDir);
        $iter = new \RecursiveDirectoryIterator($sourceDir);
        $iter = new \RecursiveIteratorIterator($iter);

        $this->startSection('autoloader');
        $this->debug('Creating classmap autoloader');
        $this->debug("Collecting valid PHP files from {$this->stageDir}");

        $classMap = [];
        foreach ($iter as $file) {
            if ($file->getExtension() == 'php') {
                $location = str_replace($this->stageDir . '/', '', (string) $file);
                $className = str_replace('/', '\\', $location);
                $className = substr($className, 0, -4);
                $classMap[$className] = "__DIR__ . '/$location'";
                $this->debug("Found $className");
            }
        }

        $destFile = $this->stageDir . '/autoloader.php';
        $this->debug("Writing autoloader to {$destFile}");

        if (!($h = fopen($destFile, 'w'))) {
            throw new \RuntimeException('Unable to open file for writing');
        }

        $this->debug('Writing classmap files');
        fwrite($h, "<?php\n\n");
        fwrite($h, "\$mapping = [\n");
        foreach ($classMap as $c => $f) {
            fwrite($h, "    '$c' => $f,\n");
        }
        fwrite($h, "];\n\n");
        fwrite($h, <<<EOT
spl_autoload_register(function (\$class) use (\$mapping) {
    if (isset(\$mapping[\$class])) {
        include \$mapping[\$class];
    }
}, true);

EOT
        );

        fwrite($h, "\n");

        $this->debug('Writing automatically included files');
        foreach ($files as $file) {
            fwrite($h, "require __DIR__ . '/$file';\n");
        }

        fclose($h);

        $this->endSection();
    }

    /**
     * Creates a default stub for the phar.
     *
     * @param $dest
     *
     * @return string
     */
    private function createStub($dest)
    {
        $this->startSection('stub');
        $this->debug("Creating phar stub at $dest");
        $alias = basename($dest);
        $project = str_replace('.phar', '', strtoupper($alias));
        $stub  = "<?php\n";
        $stub .= "define('$project', true);\n";
        $stub .= "require 'phar://$alias/autoloader.php';\n";
        $stub .= "__HALT_COMPILER();\n";
        $this->endSection();

        return $stub;
    }

    /**
     * Creates a phar that automatically registers an autoloader.
     *
     * @param string $dest Where to save the file. The basename of the file is
     *                     also used as the alias name in the phar
     *                     (e.g., /path/to/guzzle.phar => guzzle.phar).
     * @param null   $stub The path to the phar stub file. Pass or leave null
     *                     to automatically have one created for you.
     */
    public function createPhar($dest, $stub = null)
    {
        $this->startSection('phar');
        $this->debug("Creating phar file at $dest");
        $phar = new \Phar($dest, 0, basename($dest));
        $phar->buildFromDirectory($this->stageDir);

        if (!$stub) {
            $stub = $this->createStub($dest);
        }

        $phar->setStub($stub);
        $this->debug("Created phar at $dest");
        $this->endSection();
    }

    /**
     * Creates a zip file containing the staging files and a generated
     * classmap autoloader.
     *
     * @param string $dest Where to save the zip file
     */
    public function createZip($dest)
    {
        $this->startSection('zip');
        $this->debug("Creating a zip file at $dest");
        chdir($this->stageDir);
        $this->exec("zip -r $dest ./");
        $this->debug("  > Created at $dest");
        chdir(__DIR__);
        $this->endSection();
    }
}
