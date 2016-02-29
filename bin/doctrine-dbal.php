<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

use Symfony\Component\Console\Helper\HelperSet;
use Doctrine\DBAL\Tools\Console\ConsoleRunner;

$files       = array(__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php');
$loader      = null;
$cwd         = getcwd();
$directories = array($cwd, $cwd . DIRECTORY_SEPARATOR . 'config');
$configFile  = null;

foreach ($files as $file) {
    if (file_exists($file)) {
        $loader = require $file;

        break;
    }
}

if ( ! $loader) {
    throw new RuntimeException('vendor/autoload.php could not be found. Did you run `php composer.phar install`?');
}

foreach ($directories as $directory) {
    $configFile = $directory . DIRECTORY_SEPARATOR . 'cli-config.php';

    if (file_exists($configFile)) {
        break;
    }
}

if ( ! file_exists($configFile)) {
    ConsoleRunner::printCliConfigTemplate();

    exit(1);
}

if ( ! is_readable($configFile)) {
    echo 'Configuration file [' . $configFile . '] does not have read permission.' . PHP_EOL;

    exit(1);
}

$commands  = array();
$helperSet = require $configFile;

if ( ! $helperSet instanceof HelperSet) {
    foreach ($GLOBALS as $helperSetCandidate) {
        if ($helperSetCandidate instanceof HelperSet) {
            $helperSet = $helperSetCandidate;

            break;
        }
    }
}

ConsoleRunner::run($helperSet, $commands);
