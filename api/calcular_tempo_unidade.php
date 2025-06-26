<?php
require_once __DIR__ . '/config.php';

/**
 * Soma os dias de andamento para cada unidade.
 * Espera um array de entradas com chaves 'unidade' e 'dias'.
 *
 * @param array $andamentos
 * @return array
 */
function calcularDiasPorUnidade(array $andamentos): array
{
    $totais = [];
    foreach ($andamentos as $item) {
        if (!isset($item['unidade']) || !isset($item['dias'])) {
            continue;
        }
        $unidade = $item['unidade'];
        $dias = (int)$item['dias'];
        if (!isset($totais[$unidade])) {
            $totais[$unidade] = 0;
        }
        $totais[$unidade] += $dias;
    }
    return $totais;
}

$processoId = $_GET['processo_id'] ?? ($argv[1] ?? null);
if (!$processoId) {
    echo "Parâmetro processo_id obrigatório\n";
    exit(1);
}

try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $stmt = $pdo->prepare('SELECT andamentos_json FROM processo_andamentos WHERE processo_id = ?');
    $stmt->execute([$processoId]);

    $totais = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $andamentos = json_decode($row['andamentos_json'], true);
        if (is_array($andamentos)) {
            $parciais = calcularDiasPorUnidade($andamentos);
            foreach ($parciais as $unidade => $dias) {
                if (!isset($totais[$unidade])) {
                    $totais[$unidade] = 0;
                }
                $totais[$unidade] += $dias;
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($totais, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}