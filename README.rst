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

Tutorial
--------

The following example demonstrates how Guzzle uses this project.
For this example, assume this script is in ``guzzlehttp/src/build/``.

Get Burgomaster
~~~~~~~~~~~~~~~

Before running your packaging script, you'll need a copy of Burgomaster. This
can be done using composer (mtdowling/burgomaster) or just creating a Makefile
that downloads the Burgomaster.php script.

First, create the following Makefile in your project's root directory:

.. code-block:: makefile

    package: burgomaster
    	php build/packager.php

    burgomaster:
        mkdir -p build/artifacts
        curl -s https://raw.githubusercontent.com/mtdowling/Burgomaster/0.0.1/src/Burgomaster.php > build/artifacts/Burgomaster.php

.. note::

    You can substitute the above URL to use a different tag than ``0.0.1``.
    Look at `Burgomaster's releases <https://github.com/mtdowling/Burgomaster/releases>`_
    for a list of available tags.

Create a packager.php script
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Now you need to write a ``packager.php`` script, typically located in the
``build/`` directory of a project. Here's what Guzzle's looks like.

.. code-block:: php

    <?php
    require __DIR__ . '/artifacts/Burgomaster.php';

    // Creating staging directory at guzzlehttp/src/build/artifacts/staging.
    $stageDirectory = __DIR__ . '/artifacts/staging';
    // The root of the project is up one directory from the current directory.
    $projectRoot = __DIR__ . '/../';
    $packager = new \Burgomaster($stageDirectory, $projectRoot);

    // Copy basic files to the stage directory. Note that we have chdir'd onto
    // the $projectRoot directory, so use relative paths.
    foreach (['README.md', 'LICENSE'] as $file) {
        $packager->deepCopy($file, $file);
    }

    // Copy each dependency to the staging directory. Copy *.php and *.pem files.
    $packager->recursiveCopy('src', 'GuzzleHttp', ['php', 'pem']);
    $packager->recursiveCopy('vendor/guzzlehttp/streams/src', 'GuzzleHttp/Stream');
    // Create the classmap autoloader, and instruct the autoloader to
    // automatically require the 'GuzzleHttp/functions.php' script.
    $packager->createAutoloader(['GuzzleHttp/functions.php']);
    // Create a phar file from the staging directory at a specific location
    $packager->createPhar(__DIR__ . '/artifacts/guzzle.phar');
    // Create a zip file from the staging directory at a specific location
    $packager->createZip(__DIR__ . '/artifacts/guzzle.zip');

As you can see, create a ``packager.php`` script is simply a series of actions
taken that just uses Burgomaster to help with some common tasks like creating
a staging directory, building an autoloader, creating a zip, and creating a
phar.

make package
~~~~~~~~~~~~

Now that you've made your ``packager.php`` script, just run the ``packge``
Makefile target from the command line.

::

    make package

GitHub Releases
---------------

Now that you've got an easy way to package a release, you should setup your
packaging script to be automatically built and deployed to
`GitHub releases <https://developer.github.com/v3/repos/releases/>`_ using
Travis-CI's `GitHub releases deploy <http://docs.travis-ci.com/user/deployment/releases/>`_
target so that a phar and zip is uploaded when you push a tag to your
repository.
