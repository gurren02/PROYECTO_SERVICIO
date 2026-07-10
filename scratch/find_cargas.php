<?php
$content = file_get_contents('view/comparacion.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (stripos($line, 'cargas_directas') !== false || stripos($line, 'cargas dir') !== false) {
        echo "Línea " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
