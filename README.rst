===========
Burgomaster
===========

Master of towns, burgers, and creating phars and zips for PHP applications.

This script can be used to:

1. Easily create a staging directory for your package.
2. Build a class-map autoloader of all of your PHP files.
3. Create a zip file containing your project, its dependencies, and an
   autoloader.
4. Create a phar file that contains all of your project's dependencies and
   registers an autoloader when it's loaded.

This project will likely never become more than a single file containing a
single class, so feel free to just copy and paste that file into your project
rather than pulling in a new dependency just for builds.

Example usage
-------------

The following example demonstrates how Guzzle uses this project.
For this example, assume this script is in ``guzzlehttp/src/build/``.

.. code-block:: php

    // Copy Burgomaster if it is not present
    $packagerScript = __DIR__ . '/artifacts/Packager.php';
    $packagerSource = 'https://raw.githubusercontent.com/mtdowling/Burgomaster/a4bc5e5600e07436187282fca059755161f8314e/src/Packager.php';

    if (!file_exists($packagerScript)) {
        echo "Retrieving Burgomaster from $packagerSource\n";
        if (!is_dir(dirname($packagerScript))) {
            mkdir(dirname($packagerScript)) or die('Unable to create dir');
        }
        file_put_contents($packagerScript, file_get_contents($packagerSource));
        echo "> Downloaded Burgomaster\n\n";
    }

    require $packagerScript;

    // Creating staging directory at guzzlehttp/src/build/artifacts/staging.
    $stageDirectory = __DIR__ . '/artifacts/staging';

    // The root of the project is up one directory from the current directory.
    $projectRoot = __DIR__ . '/../';

    $packager = new \Burgomaster\Packager($stageDirectory, $projectRoot);

    // Copy basic files to the stage directory. Note that we have chdir'd onto
    // the $projectRoot directory, so use relative paths.
    foreach (['README.md', 'LICENSE'] as $file) {
        $packager->deepCopy($file, $file);
    }

    // Copy each dependency to the staging directory
    $packager->recursiveCopy('src', 'GuzzleHttp');
    $packager->recursiveCopy('vendor/guzzlehttp/streams/src', 'GuzzleHttp/Stream');

    // Create the classmap autoloader, and instruct the autoloader to
    // automatically require the 'GuzzleHttp/functions.php' script.
    $packager->createAutoloader(['GuzzleHttp/functions.php']);

    // Create a phar file from the staging directory at a specific location
    $packager->createPhar(__DIR__ . '/artifacts/guzzle.phar');

    // Create a zip file from the staging directory at a specific location
    $packager->createZip(__DIR__ . '/artifacts/guzzle.zip');
