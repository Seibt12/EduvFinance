<?php
// ============================================================
// GET /backend/api/simulator/history.php
//
// Returns the 10 most recent simulations for the logged-in user.
//
// Response:
//   { success, simulations: [ { id, investment_type,
//     initial_capital, monthly_contribution, period_months,
//     monthly_rate, final_amount, total_invested, total_profit,
//     created_at }, ... ] }
// ============================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

setCORSHeaders();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$userId = (int) $_SESSION['user_id'];

try {
    $conn = getConnection();

    $stmt = $conn->prepare("
        SELECT  id,
                investment_type,
                initial_capital,
                monthly_contribution,
                period_months,
                monthly_rate,
                final_amount,
                total_invested,
                total_profit,
                created_at
        FROM    investment_simulations
        WHERE   user_id = ?
        ORDER BY created_at DESC
        LIMIT   10
    ");

    $stmt->execute([$userId]);
    $simulations = $stmt->fetchAll();

    // Cast numeric strings to floats for clean JSON output
    foreach ($simulations as &$sim) {
        $sim['initial_capital']      = (float) $sim['initial_capital'];
        $sim['monthly_contribution'] = (float) $sim['monthly_contribution'];
        $sim['monthly_rate']         = (float) $sim['monthly_rate'];
        $sim['final_amount']         = (float) $sim['final_amount'];
        $sim['total_invested']       = (float) $sim['total_invested'];
        $sim['total_profit']         = (float) $sim['total_profit'];
        $sim['period_months']        = (int)   $sim['period_months'];
    }
    unset($sim);

    jsonResponse(['success' => true, 'simulations' => $simulations]);

} catch (PDOException $e) {
    error_log('[EduFinance][Simulator] Erro ao buscar histórico: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao carregar histórico.'], 500);
}
