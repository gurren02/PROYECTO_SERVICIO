<?php
// Cache Helper for SEDEFAC reports

function get_cache_file($page_name, $params) {
    $cache_dir = __DIR__ . '/cache';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0777, true);
    }
    // Create a unique key based on the parameters
    $key = md5(serialize($params));
    return $cache_dir . '/cache_' . $page_name . '_' . $key . '.json';
}

function load_cache($page_name, $params) {
    // If clear_cache is requested, skip loading from cache
    if (isset($_GET['clear_cache']) && $_GET['clear_cache'] == '1') {
        return null;
    }
    
    $file = get_cache_file($page_name, $params);
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return null;
}

function save_cache($page_name, $params, $data) {
    $file = get_cache_file($page_name, $params);
    file_put_contents($file, json_encode($data));
}

function clear_all_cache() {
    $cache_dir = __DIR__ . '/cache';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '/*.json');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
