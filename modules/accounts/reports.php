<?php
// modules/accounts/reports.php - Financial Reporting Engine (Trial Balance, P&L, Balance Sheet, Ledger Statement)
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$page_title = "Financial Reports";
$tab = sanitize_input($_GET['tab'] ?? 'trial_balance');
$fiscal = get_active_fiscal_year($conn);

// Fetch All Ledger Balances
$sql_ledgers = "SELECT l.LedgerNumber, l.LedgerName, l.LedgerCode, lg.LedgerGroupNumber, lg.LedgerGroupName,
                (
                    COALESCE((SELECT SUM(TransactionAmount) FROM AccTransaction WHERE LedgerNumberBook = l.LedgerNumber), 0) +
                    COALESCE((SELECT SUM(LedgerDebitAmount) FROM AccTransDetails WHERE LedgerDebitAmount > 0 AND TransHeadNumber IN (SELECT TransHeadNumber FROM AccTransaction WHERE LedgerNumberBook = l.LedgerNumber)), 0)
                ) AS TotalDebit,
                (
                    COALESCE((SELECT SUM(TransactionAmount) FROM AccTransaction WHERE LedgerNumberHead = l.LedgerNumber), 0) +
                    COALESCE((SELECT SUM(LedgerCreditAmount) FROM AccTransDetails WHERE LedgerCreditAmount > 0 AND TransHeadNumber IN (SELECT TransHeadNumber FROM AccTransaction WHERE LedgerNumberHead = l.LedgerNumber)), 0)
                ) AS TotalCredit
                FROM MstrLedger l
                LEFT JOIN MstrLedgerGroup lg ON l.LedgerGroupNumber = lg.LedgerGroupNumber
                ORDER BY lg.LedgerGroupNumber ASC, l.LedgerName ASC";

$ledger_balances_res = db_query($conn, $sql_ledgers);
$ledger_data = [];
$total_trial_debit = 0;
$total_trial_credit = 0;

if ($ledger_balances_res && db_num_rows($ledger_balances_res) > 0) {
    while ($row = db_fetch_assoc($ledger_balances_res)) {
        $dr = floatval($row['TotalDebit']);
        $cr = floatval($row['TotalCredit']);
        $row['NetBalance'] = $dr - $cr;
        $ledger_data[] = $row;
        $total_trial_debit += $dr;
        $total_trial_credit += $cr;
    }
}

// Calculate P&L Totals
$total_income = 0;
$total_expenses = 0;
$income_items = [];
$expense_items = [];

foreach ($ledger_data as $l) {
    $g_num = intval($l['LedgerGroupNumber']);
    // Income groups: 3, 13
    if ($g_num == 3 || $g_num == 13) {
        $net_inc = $l['TotalCredit'] - $l['TotalDebit'];
        if ($net_inc != 0) {
            $l['PnLAmount'] = $net_inc;
            $income_items[] = $l;
            $total_income += $net_inc;
        }
    }
    // Expense groups: 4, 15
    if ($g_num == 4 || $g_num == 15) {
        $net_exp = $l['TotalDebit'] - $l['TotalCredit'];
        if ($net_exp != 0) {
            $l['PnLAmount'] = $net_exp;
            $expense_items[] = $l;
            $total_expenses += $net_exp;
        }
    }
}

$net_profit = $total_income - $total_expenses;

// Calculate Balance Sheet Items
$asset_items = [];
$liability_items = [];
$total_assets = 0;
$total_liabilities = 0;

foreach ($ledger_data as $l) {
    $g_num = intval($l['LedgerGroupNumber']);
    // Asset groups: 1, 10, 11, 12
    if ($g_num == 1 || $g_num == 10 || $g_num == 11 || $g_num == 12) {
        $bal = $l['TotalDebit'] - $l['TotalCredit'];
        if ($bal != 0) {
            $l['BSAmount'] = $bal;
            $asset_items[] = $l;
            $total_assets += $bal;
        }
    }
    // Liability / Capital groups: 2, 5, 14
    if ($g_num == 2 || $g_num == 5 || $g_num == 14) {
        $bal = $l['TotalCredit'] - $l['TotalDebit'];
        if ($bal != 0) {
            $l['BSAmount'] = $bal;
            $liability_items[] = $l;
            $total_liabilities += $bal;
        }
    }
}

// Ledger Statement Filter
$selected_ledger_id = intval($_GET['ledger_id'] ?? 1);
$ledger_stmt_res = null;
if ($selected_ledger_id > 0) {
    $sql_stmt = "SELECT t.*,
                dr_l.LedgerName as DrLedgerName,
                cr_l.LedgerName as CrLedgerName
                FROM AccTransaction t
                LEFT JOIN MstrLedger dr_l ON t.LedgerNumberBook = dr_l.LedgerNumber
                LEFT JOIN MstrLedger cr_l ON t.LedgerNumberHead = cr_l.LedgerNumber
                WHERE t.LedgerNumberBook = ? OR t.LedgerNumberHead = ?
                ORDER BY t.TransactionDate ASC, t.AccTransNumber ASC";
    $ledger_stmt_res = db_query($conn, $sql_stmt, "ii", [$selected_ledger_id, $selected_ledger_id]);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h2><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Financial Reports</h2>
    <button onclick="window.print();" class="btn btn-outline-dark"><i class="fa-solid fa-print me-1"></i>Print Report</button>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4 no-print">
    <li class="nav-item">
        <a class="nav-link <?php echo ($tab === 'trial_balance') ? 'active fw-bold' : ''; ?>" href="reports.php?tab=trial_balance">
            <i class="fa-solid fa-scale-balanced me-1"></i>Trial Balance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($tab === 'pnl') ? 'active fw-bold' : ''; ?>" href="reports.php?tab=pnl">
            <i class="fa-solid fa-chart-pie me-1"></i>Profit & Loss Statement
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($tab === 'balance_sheet') ? 'active fw-bold' : ''; ?>" href="reports.php?tab=balance_sheet">
            <i class="fa-solid fa-building-columns me-1"></i>Balance Sheet
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo ($tab === 'statement') ? 'active fw-bold' : ''; ?>" href="reports.php?tab=statement">
            <i class="fa-solid fa-list-ol me-1"></i>Ledger Statement
        </a>
    </li>
</ul>

<?php if ($tab === 'trial_balance'): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white text-center py-3">
            <h4 class="mb-0"><?php echo htmlspecialchars($company['CompanyName']); ?></h4>
            <h5 class="mb-0 text-warning">Trial Balance</h5>
            <small>Financial Year: <?php echo htmlspecialchars($fiscal['YearDescription']); ?> | As of <?php echo date('d-M-Y'); ?></small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0 align-middle">
                    <thead class="table-secondary">
                        <tr>
                            <th>Ledger #</th>
                            <th>Ledger Account Name</th>
                            <th>Account Group</th>
                            <th class="text-end">Debit Amount (Dr. ₹)</th>
                            <th class="text-end">Credit Amount (Cr. ₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ledger_data as $l): ?>
                            <?php if ($l['TotalDebit'] > 0 || $l['TotalCredit'] > 0): ?>
                                <tr>
                                    <td><code><?php echo $l['LedgerNumber']; ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($l['LedgerName']); ?></strong></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($l['LedgerGroupName'] ?? ''); ?></span></td>
                                    <td class="text-end"><?php echo ($l['TotalDebit'] > 0) ? number_format($l['TotalDebit'], 2) : '-'; ?></td>
                                    <td class="text-end"><?php echo ($l['TotalCredit'] > 0) ? number_format($l['TotalCredit'], 2) : '-'; ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-dark fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">TOTAL TRIAL BALANCE:</td>
                            <td class="text-end">₹<?php echo number_format($total_trial_debit, 2); ?></td>
                            <td class="text-end">₹<?php echo number_format($total_trial_credit, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'pnl'): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white text-center py-3">
            <h4 class="mb-0"><?php echo htmlspecialchars($company['CompanyName']); ?></h4>
            <h5 class="mb-0 text-warning">Income & Expenditure / Profit & Loss Statement</h5>
            <small>For Period Ending <?php echo date('d-M-Y'); ?></small>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- Expenditure -->
                <div class="col-md-6 border-end">
                    <h5 class="text-danger fw-bold border-bottom pb-2"><i class="fa-solid fa-arrow-trend-down me-2"></i>Expenses / Expenditure</h5>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Expense Account</th>
                                <th class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expense_items as $e): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($e['LedgerName']); ?></td>
                                    <td class="text-end">₹<?php echo number_format($e['PnLAmount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($expense_items)): ?>
                                <tr><td colspan="2" class="text-muted">No expense entries.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="fw-bold border-top">
                            <tr>
                                <td>TOTAL EXPENSES:</td>
                                <td class="text-end text-danger">₹<?php echo number_format($total_expenses, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Income -->
                <div class="col-md-6">
                    <h5 class="text-success fw-bold border-bottom pb-2"><i class="fa-solid fa-arrow-trend-up me-2"></i>Revenue / Income</h5>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Income Account</th>
                                <th class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($income_items as $i): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['LedgerName']); ?></td>
                                    <td class="text-end">₹<?php echo number_format($i['PnLAmount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($income_items)): ?>
                                <tr><td colspan="2" class="text-muted">No income entries.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="fw-bold border-top">
                            <tr>
                                <td>TOTAL INCOME:</td>
                                <td class="text-end text-success">₹<?php echo number_format($total_income, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="alert <?php echo ($net_profit >= 0) ? 'alert-success' : 'alert-danger'; ?> text-center fw-bold mt-4 mb-0 py-3 fs-5">
                NET <?php echo ($net_profit >= 0) ? 'PROFIT / SURPLUS' : 'LOSS / DEFICIT'; ?>: ₹<?php echo number_format(abs($net_profit), 2); ?>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'balance_sheet'): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white text-center py-3">
            <h4 class="mb-0"><?php echo htmlspecialchars($company['CompanyName']); ?></h4>
            <h5 class="mb-0 text-warning">Balance Sheet</h5>
            <small>As of <?php echo date('d-M-Y'); ?></small>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- Liabilities & Equity -->
                <div class="col-md-6 border-end">
                    <h5 class="text-primary fw-bold border-bottom pb-2">Liabilities & Capital</h5>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Capital & Liability Account</th>
                                <th class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liability_items as $l): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($l['LedgerName']); ?></td>
                                    <td class="text-end">₹<?php echo number_format($l['BSAmount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td><strong>Net Surplus / Profit (P&L)</strong></td>
                                <td class="text-end">₹<?php echo number_format($net_profit, 2); ?></td>
                            </tr>
                        </tbody>
                        <tfoot class="fw-bold border-top">
                            <tr>
                                <td>TOTAL LIABILITIES & CAPITAL:</td>
                                <td class="text-end text-primary">₹<?php echo number_format($total_liabilities + $net_profit, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Assets -->
                <div class="col-md-6">
                    <h5 class="text-success fw-bold border-bottom pb-2">Assets</h5>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Asset Account</th>
                                <th class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($asset_items as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a['LedgerName']); ?></td>
                                    <td class="text-end">₹<?php echo number_format($a['BSAmount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="fw-bold border-top">
                            <tr>
                                <td>TOTAL ASSETS:</td>
                                <td class="text-end text-success">₹<?php echo number_format($total_assets, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'statement'): ?>
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="reports.php" class="row g-3 align-items-center">
                <input type="hidden" name="tab" value="statement">
                <div class="col-md-8">
                    <label class="form-label fw-bold small">Select Ledger Account</label>
                    <select name="ledger_id" class="form-select" onchange="this.form.submit();">
                        <?php foreach ($ledger_data as $l): ?>
                            <option value="<?php echo $l['LedgerNumber']; ?>" <?php echo ($selected_ledger_id == $l['LedgerNumber']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($l['LedgerName']); ?> (Group: <?php echo htmlspecialchars($l['LedgerGroupName'] ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>View Statement</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white text-center py-3">
            <h4 class="mb-0"><?php echo htmlspecialchars($company['CompanyName']); ?></h4>
            <h5 class="mb-0 text-warning">Ledger Statement Account History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-secondary">
                        <tr>
                            <th>Date</th>
                            <th>Voucher #</th>
                            <th>Particulars / Corresponding Account</th>
                            <th>Narration</th>
                            <th class="text-end">Debit (Dr. ₹)</th>
                            <th class="text-end">Credit (Cr. ₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $running_dr = 0;
                            $running_cr = 0;
                        ?>
                        <?php if ($ledger_stmt_res && db_num_rows($ledger_stmt_res) > 0): ?>
                            <?php while ($row = db_fetch_assoc($ledger_stmt_res)): ?>
                                <?php
                                    $is_debit = ($row['LedgerNumberBook'] == $selected_ledger_id);
                                    $amt = floatval($row['TransactionAmount']);
                                    $dr_val = $is_debit ? $amt : 0;
                                    $cr_val = !$is_debit ? $amt : 0;
                                    $running_dr += $dr_val;
                                    $running_cr += $cr_val;
                                    $corr_account = $is_debit ? $row['CrLedgerName'] : $row['DrLedgerName'];
                                ?>
                                <tr>
                                    <td><?php echo date('d-M-Y', strtotime($row['TransactionDate'])); ?></td>
                                    <td><span class="badge bg-secondary">VOU-<?php echo $row['TransHeadNumber']; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($corr_account ?? 'General'); ?></strong></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($row['TransactionRemarks'] ?? 'N/A'); ?></small></td>
                                    <td class="text-end text-danger"><?php echo ($dr_val > 0) ? number_format($dr_val, 2) : '-'; ?></td>
                                    <td class="text-end text-success"><?php echo ($cr_val > 0) ? number_format($cr_val, 2) : '-'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No transactions found for this ledger account.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-dark fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">TOTAL STATEMENT BALANCE:</td>
                            <td class="text-end">₹<?php echo number_format($running_dr, 2); ?></td>
                            <td class="text-end">₹<?php echo number_format($running_cr, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
