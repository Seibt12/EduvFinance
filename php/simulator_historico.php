<?php
require_once __DIR__ . '/valida_sessao.php';
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$idUsuario = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT id, investment_type, initial_capital, monthly_contribution,
           period_months, monthly_rate, final_amount, total_invested,
           total_profit, created_at
    FROM investment_simulations
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$idUsuario]);
$simulacoes = $stmt->fetchAll();

foreach ($simulacoes as &$sim) {
    $sim['initial_capital']      = (float)$sim['initial_capital'];
    $sim['monthly_contribution'] = (float)$sim['monthly_contribution'];
    $sim['monthly_rate']         = (float)$sim['monthly_rate'];
    $sim['final_amount']         = (float)$sim['final_amount'];
    $sim['total_invested']       = (float)$sim['total_invested'];
    $sim['total_profit']         = (float)$sim['total_profit'];
    $sim['period_months']        = (int)$sim['period_months'];
}
unset($sim);

echo json_encode(['success' => true, 'simulations' => $simulacoes]);
