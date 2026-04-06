<?php
// Calcula uma simulação de investimento com juros compostos mês a mês.
// Salva o resultado no banco e devolve os dados necessários para o gráfico.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$dados = getJsonBody();

// Taxa mensal de cada tipo de investimento
$taxas = [
    'savings' => 0.005,  // Poupança  0,5%
    'cdb'     => 0.008,  // CDB       0,8%
    'stocks'  => 0.012,  // Ações     1,2%
    'crypto'  => 0.020,  // Cripto    2,0%
];

// Lê e converte os campos do body
$capitalInicial   = isset($dados['initial_capital'])      ? (float) $dados['initial_capital']      : null;
$aporteMensal     = isset($dados['monthly_contribution']) ? (float) $dados['monthly_contribution'] : 0.0;
$meses            = isset($dados['period_months'])        ? (int)   $dados['period_months']        : null;
$tipoInvestimento = trim($dados['investment_type'] ?? '');

// Validações — qualquer erro para a execução e devolve a mensagem
if ($capitalInicial === null || $capitalInicial < 0) {
    jsonResponse(['success' => false, 'message' => 'Capital inicial inválido (deve ser >= 0).'], 422);
}

if ($aporteMensal < 0) {
    jsonResponse(['success' => false, 'message' => 'Aporte mensal não pode ser negativo.'], 422);
}

if ($capitalInicial === 0.0 && $aporteMensal === 0.0) {
    jsonResponse(['success' => false, 'message' => 'Informe um capital inicial ou aporte mensal maior que zero.'], 422);
}

if ($meses === null || $meses < 1 || $meses > 600) {
    jsonResponse(['success' => false, 'message' => 'Período inválido. Informe entre 1 e 600 meses.'], 422);
}

if (!array_key_exists($tipoInvestimento, $taxas)) {
    jsonResponse(['success' => false, 'message' => 'Tipo de investimento inválido.'], 422);
}

$taxa = $taxas[$tipoInvestimento];

// Cálculo mês a mês — guarda os valores para o gráfico
// Fórmula: saldo = saldo * (1 + taxa) + aporte
$evolucaoPatrimonio = [];  // saldo total a cada mês
$evolucaoInvestido  = [];  // quanto foi investido (sem juros) a cada mês

$saldo = (float) $capitalInicial;

for ($mes = 1; $mes <= $meses; $mes++) {
    $saldo          = $saldo * (1 + $taxa) + $aporteMensal;
    $totalInvestido = $capitalInicial + ($aporteMensal * $mes);

    $evolucaoPatrimonio[] = round($saldo, 2);
    $evolucaoInvestido[]  = round($totalInvestido, 2);
}

$valorFinal     = round($saldo, 2);
$totalInvestido = round($capitalInicial + ($aporteMensal * $meses), 2);
$rendimento     = round($valorFinal - $totalInvestido, 2);

// Salva no banco para o histórico do aluno.
// Se der erro aqui, não interrompemos — o resultado já foi calculado.
$idUsuario = (int) $_SESSION['user_id'];

try {
    $conn = getConnection();
    $stmt = $conn->prepare("
        INSERT INTO investment_simulations
            (user_id, investment_type, initial_capital, monthly_contribution,
             period_months, monthly_rate, final_amount, total_invested, total_profit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $idUsuario,
        $tipoInvestimento,
        $capitalInicial,
        $aporteMensal,
        $meses,
        $taxa,
        $valorFinal,
        $totalInvestido,
        $rendimento,
    ]);
} catch (PDOException $e) {
    error_log('[Simulator] Erro ao salvar simulação: ' . $e->getMessage());
}

jsonResponse([
    'success'       => true,
    'totalInvested' => $totalInvestido,
    'totalProfit'   => $rendimento,
    'finalAmount'   => $valorFinal,
    'monthlyRate'   => $taxa,
    'evolution'     => $evolucaoPatrimonio,
    'investedEvol'  => $evolucaoInvestido,
]);
