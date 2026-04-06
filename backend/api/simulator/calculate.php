<?php
// ============================================================
// POST /backend/api/simulator/calculate.php
//
// Receives investment parameters, validates them, runs the
// compound-interest formula month-by-month, saves the result
// to investment_simulations, and returns the evolution data.
//
// Body (JSON):
//   initial_capital      float   >= 0
//   investment_type      string  savings|cdb|stocks|crypto
//   period_months        int     1–600
//   monthly_contribution float   >= 0 (optional, default 0)
//
// Response:
//   { success, totalInvested, totalProfit, finalAmount,
//     monthlyRate, evolution[], investedEvol[] }
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$data = getJsonBody();

// ----------------------------------------------------------
// Rates — monthly compound rates per investment type
// ----------------------------------------------------------
const INVESTMENT_RATES = [
    'savings' => 0.005,  // Poupança  0.5%
    'cdb'     => 0.008,  // CDB       0.8%
    'stocks'  => 0.012,  // Ações     1.2%
    'crypto'  => 0.020,  // Cripto    2.0%
];

// ----------------------------------------------------------
// Input parsing
// ----------------------------------------------------------
$initialCapital      = isset($data['initial_capital'])      ? (float) $data['initial_capital']      : null;
$monthlyContribution = isset($data['monthly_contribution']) ? (float) $data['monthly_contribution'] : 0.0;
$periodMonths        = isset($data['period_months'])        ? (int)   $data['period_months']        : null;
$investmentType      = trim($data['investment_type'] ?? '');

// ----------------------------------------------------------
// Server-side validation
// ----------------------------------------------------------
if ($initialCapital === null || $initialCapital < 0) {
    jsonResponse(['success' => false, 'message' => 'Capital inicial inválido (deve ser >= 0).'], 422);
}

if ($monthlyContribution < 0) {
    jsonResponse(['success' => false, 'message' => 'Aporte mensal não pode ser negativo.'], 422);
}

if ($initialCapital === 0.0 && $monthlyContribution === 0.0) {
    jsonResponse(['success' => false, 'message' => 'Informe um capital inicial ou aporte mensal maior que zero.'], 422);
}

if ($periodMonths === null || $periodMonths < 1 || $periodMonths > 600) {
    jsonResponse(['success' => false, 'message' => 'Período inválido. Informe entre 1 e 600 meses.'], 422);
}

if (!array_key_exists($investmentType, INVESTMENT_RATES)) {
    jsonResponse(['success' => false, 'message' => 'Tipo de investimento inválido.'], 422);
}

$rate = INVESTMENT_RATES[$investmentType];

// ----------------------------------------------------------
// Compound interest calculation — month by month
//
// Formula: M = P*(1+i)^n + PMT * ((1+i)^n - 1) / i
// We iterate so we can store each month's value for the chart.
// ----------------------------------------------------------
$evolution    = [];   // total balance per month
$investedEvol = [];   // cumulative invested amount per month

$balance = (float) $initialCapital;

for ($month = 1; $month <= $periodMonths; $month++) {
    $balance       = $balance * (1 + $rate) + $monthlyContribution;
    $totalInvested = $initialCapital + ($monthlyContribution * $month);

    $evolution[]    = round($balance, 2);
    $investedEvol[] = round($totalInvested, 2);
}

$finalAmount        = round($balance, 2);
$totalInvestedFinal = round($initialCapital + ($monthlyContribution * $periodMonths), 2);
$totalProfit        = round($finalAmount - $totalInvestedFinal, 2);

// ----------------------------------------------------------
// Persist simulation — failures here are logged but do NOT
// break the API response (calculation is still returned).
// ----------------------------------------------------------
$userId = (int) $_SESSION['user_id'];

try {
    $conn = getConnection();

    $stmt = $conn->prepare("
        INSERT INTO investment_simulations
            (user_id, investment_type, initial_capital, monthly_contribution,
             period_months, monthly_rate, final_amount, total_invested, total_profit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $investmentType,
        $initialCapital,
        $monthlyContribution,
        $periodMonths,
        $rate,
        $finalAmount,
        $totalInvestedFinal,
        $totalProfit,
    ]);
} catch (PDOException $e) {
    // Non-fatal: log and continue
    error_log('[EduFinance][Simulator] Erro ao salvar simulação: ' . $e->getMessage());
}

// ----------------------------------------------------------
// Response
// ----------------------------------------------------------
jsonResponse([
    'success'       => true,
    'totalInvested' => $totalInvestedFinal,
    'totalProfit'   => $totalProfit,
    'finalAmount'   => $finalAmount,
    'monthlyRate'   => $rate,
    'evolution'     => $evolution,
    'investedEvol'  => $investedEvol,
]);
