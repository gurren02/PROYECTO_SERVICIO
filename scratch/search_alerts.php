<?php
$content = file_get_contents('view/falsos.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (stripos($line, 'alert') !== false) {
        echo "Línea " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
