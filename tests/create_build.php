<?php
require __DIR__ . '/../src/Burgomaster.php';

$buildDir = sys_get_temp_dir() . '/bbuild';
$stageDirectory = sys_get_temp_dir() . '/bstage';
$projectRoot = __DIR__ . '/../';
$packager = new \Burgomaster($stageDirectory, $projectRoot);

foreach (array('README.rst', 'LICENSE') as $file) {
    $packager->deepCopy($file, $file);
}

$packager->recursiveCopy('src', 'src');
$packager->createAutoloader(array(), 'test-autoloader.php');
$packager->createPhar("$buildDir/bg.phar", null, 'test-autoloader.php');
$packager->createZip("$buildDir/bg.zip");
