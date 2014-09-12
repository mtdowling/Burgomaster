<?php

$buildDir = sys_get_temp_dir() . '/bbuild';
$stageDirectory = sys_get_temp_dir() . '/bstage';
$zipextract = sys_get_temp_dir() . '/bzip';

echo "Ensuring the staging directory was created correctly\n";
assert(is_dir($stageDirectory), 'Staging directory not found');
assert(is_file("$stageDirectory/test-autoloader.php"), 'Autoloader exists');
assert(is_file("$stageDirectory/LICENSE"), 'License exists');
assert(is_file("$stageDirectory/README.rst"), 'README exists');
assert(is_file("$stageDirectory/src/Burgomaster.php"), 'Script exists');

echo "Ensuring the build directory was created correctly\n";
assert(is_dir($buildDir), 'Build directory not found');
assert(is_file("$buildDir/bg.phar"), 'phar not found');
assert(is_file("$buildDir/bg.zip"), 'zip not found');

echo "Ensuring the zip was created correctly\n";

if (is_dir($zipextract)) {
    echo "Clearing out previous directory\n";
    passthru("rm -rf {$zipextract}");
}

exec("mkdir -p {$zipextract}");
exec("cd {$zipextract} && cp {$buildDir}/bg.zip ./ && unzip bg.zip");
assert(is_file("$zipextract/test-autoloader.php"), 'Autoloader exists');
assert(is_file("$zipextract/LICENSE"), 'License exists');
assert(is_file("$zipextract/README.rst"), 'README exists');
assert(is_file("$zipextract/src/Burgomaster.php"), 'Script exists');

echo "Ensuring the phar was created correctly\n";
require "$buildDir/bg.phar";
assert(class_exists('Burgomaster'));
