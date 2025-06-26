<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
function usage()
{
    // Caminho atualizado para execucao no CLI
    echo "Usage (CLI): php import_json.php <file.json>\n";
    echo "Usage (HTTP POST): send file in 'file' field\n";
    exit(1);
}

// Determine file path based on context
if (php_sapi_name() === 'cli') {
    $file = $argv[1] ?? null;
} else {
    $file = $_FILES['file']['tmp_name'] ?? null;
}

if (!$file || !file_exists($file)) {
    usage();
}

$json = file_get_contents($file);
$data = json_decode($json, true);
if (!$data || !isset($data['nup'], $data['processo_id'], $data['timestamp'], $data['total_andamentos'], $data['andamentos'])) {
    exit("Invalid JSON structure\n");
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare(
        "INSERT INTO processo_andamentos (nup, processo_id, timestamp, total_andamentos, andamentos_json)
         VALUES (:nup, :proc_id, :ts, :total, :json)
         ON DUPLICATE KEY UPDATE
            timestamp = VALUES(timestamp),
            total_andamentos = VALUES(total_andamentos),
            andamentos_json = VALUES(andamentos_json)"
    );

    $stmt->execute([
        ':nup' => $data['nup'],
        ':proc_id' => $data['processo_id'],
        ':ts' => $data['timestamp'],
        ':total' => $data['total_andamentos'],
        ':json' => json_encode($data['andamentos'])
    ]);

    echo "Importação bem sucedida\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}