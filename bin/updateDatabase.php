<?php

require_once __DIR__ . '/../vendor/autoload.php';

use fkooman\Config\Config;
use fkooman\OAuth\Client\PdoStorage;

$config = Config::fromYamlFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "config.yaml");
$storage = new PdoStorage($config->getSection("storage"));

$d = $storage->getChangeInfo();
$patchLevel = FALSE === $d ? 0 : $d['patch_number'];

echo "[INFO] current database patch level: " . $patchLevel . PHP_EOL;

// look for all SQL patch files and execute them in order
foreach (glob("schema/updates/*.sql") as $filename) {
    list($patchNumber, $description) = explode("_", basename($filename, ".sql"), 2);
    if ($patchNumber > $patchLevel) {
        echo "[UPDATE] applying " . $filename . "..." . PHP_EOL;
        $sql = file_get_contents($filename);
        $storage->dbQuery($sql);
        $storage->addChangeInfo($patchNumber, $description);
    }
}
echo "[INFO] done applying patches!" . PHP_EOL;
