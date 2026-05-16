<?php
require_once __DIR__ . '/valida_sessao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$taxas = [
    'savings' => 0.005,
    'cdb'     => 0.008,
    'stocks'  => 0.012,
    'crypto'  => 0.020,
];

$raw  = file_get_contents('php://input');
$dados = json_decode($raw, true) ?: [];

// Suporte tanto a JSON (fetch) quanto a POST form
if (empty($dados)) {
    $dados = $_POST;
}

$capitalInicial   = isset($dados['initial_capital'])      ? (float)$dados['initial_capital']      : null;
$aporteMensal     = isset($dados['monthly_contribution']) ? (float)$dados['monthly_contribution'] : 0.0;
$meses            = isset($dados['period_months'])        ? (int)$dados['period_months']          : null;
$tipoInvestimento = trim($dados['investment_type'] ?? '');

if ($capitalInicial === null || $capitalInicial < 0) {
    echo json_encode(['success' => false, 'message' => 'Capital inicial inválido (deve ser >= 0).']);
    exit;
}
if ($aporteMensal < 0) {
    echo json_encode(['success' => false, 'message' => 'Aporte mensal não pode ser negativo.']);
    exit;
}
if ($capitalInicial === 0.0 && $aporteMensal === 0.0) {
    echo json_encode(['success' => false, 'message' => 'Informe um capital inicial ou aporte mensal maior que zero.']);
    exit;
}
if ($meses === null || $meses < 1 || $meses > 600) {
    echo json_encode(['success' => false, 'message' => 'Período inválido. Informe entre 1 e 600 meses.']);
    exit;
}
if (!array_key_exists($tipoInvestimento, $taxas)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de investimento inválido.']);
    exit;
}

$taxa = $taxas[$tipoInvestimento];
$evolucaoPatrimonio = [];
$evolucaoInvestido  = [];
$saldo = (float)$capitalInicial;

for ($mes = 1; $mes <= $meses; $mes++) {
    $saldo          = $saldo * (1 + $taxa) + $aporteMensal;
    $totalInvestido = $capitalInicial + ($aporteMensal * $mes);
    $evolucaoPatrimonio[] = round($saldo, 2);
    $evolucaoInvestido[]  = round($totalInvestido, 2);
}

$valorFinal     = round($saldo, 2);
$totalInvestido = round($capitalInicial + ($aporteMensal * $meses), 2);
$rendimento     = round($valorFinal - $totalInvestido, 2);

$idUsuario = (int)$_SESSION['user_id'];
try {
    require_once __DIR__ . '/conexao.php';
    $conn->prepare("
        INSERT INTO investment_simulations
            (user_id, investment_type, initial_capital, monthly_contribution,
             period_months, monthly_rate, final_amount, total_invested, total_profit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$idUsuario, $tipoInvestimento, $capitalInicial, $aporteMensal, $meses, $taxa, $valorFinal, $totalInvestido, $rendimento]);
} catch (PDOException $e) {
    error_log('[Simulator] Erro ao salvar: ' . $e->getMessage());
}

echo json_encode([
    'success'       => true,
    'totalInvested' => $totalInvestido,
    'totalProfit'   => $rendimento,
    'finalAmount'   => $valorFinal,
    'monthlyRate'   => $taxa,
    'evolution'     => $evolucaoPatrimonio,
    'investedEvol'  => $evolucaoInvestido,
]);
