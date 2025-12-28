    /**
     * Helper to flatten balance sheet data for CSV.
     */
    private function flattenForCsv(array $data): array
    {
        $rows = [];
        
        // Assets
        foreach ($data['assets']['current'] as $acc) {
            $rows[] = ['Assets', 'Current Assets', $acc['account_code'], $acc['account_name'], $acc['amount_cents'] / 100];
        }
        foreach ($data['assets']['fixed'] as $acc) {
            $rows[] = ['Assets', 'Fixed Assets', $acc['account_code'], $acc['account_name'], $acc['amount_cents'] / 100];
        }

        // Liabilities
        foreach ($data['liabilities']['current'] as $acc) {
            $rows[] = ['Liabilities', 'Current Liabilities', $acc['account_code'], $acc['account_name'], $acc['amount_cents'] / 100];
        }
        foreach ($data['liabilities']['long_term'] as $acc) {
            $rows[] = ['Liabilities', 'Long Term Liabilities', $acc['account_code'], $acc['account_name'], $acc['amount_cents'] / 100];
        }

        // Equity
        foreach ($data['equity']['accounts'] as $acc) {
            $rows[] = ['Equity', 'Equity', $acc['account_code'], $acc['account_name'], $acc['amount_cents'] / 100];
        }
        $rows[] = ['Equity', 'Retained Earnings', '', 'Retained Earnings', $data['equity']['retained_earnings_cents'] / 100];

        return $rows;
    }

    /**
     * Helper to generate HTML for PDF.
     */
    private function generateHtml(array $data, ?string $date): string
    {
        // Simple HTML generation
        $dateStr = $date ?? date('Y-m-d');
        $html = "<html><head><style>body { font-family: sans-serif; } table { width: 100%; border-collapse: collapse; } th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; } .amount { text-align: right; } .header { text-align: center; margin-bottom: 20px; }</style></head><body>";
        $html .= "<div class='header'><h1>Balance Sheet</h1><p>As of Date: {$dateStr}</p></div>";
        
        $html .= "<h2>Assets</h2>";
        $html .= "<table><tr><th>Code</th><th>Account</th><th class='amount'>Amount</th></tr>";
        foreach ($data['assets']['current'] as $acc) {
            $html .= "<tr><td>{$acc['account_code']}</td><td>{$acc['account_name']}</td><td class='amount'>" . number_format($acc['amount_cents'] / 100, 2) . "</td></tr>";
        }
        foreach ($data['assets']['fixed'] as $acc) {
            $html .= "<tr><td>{$acc['account_code']}</td><td>{$acc['account_name']}</td><td class='amount'>" . number_format($acc['amount_cents'] / 100, 2) . "</td></tr>";
        }
        $html .= "<tr><td></td><td><strong>Total Assets</strong></td><td class='amount'><strong>" . number_format($data['assets']['total_cents'] / 100, 2) . "</strong></td></tr>";
        $html .= "</table>";

        $html .= "<h2>Liabilities</h2>";
        $html .= "<table><tr><th>Code</th><th>Account</th><th class='amount'>Amount</th></tr>";
        foreach ($data['liabilities']['current'] as $acc) {
            $html .= "<tr><td>{$acc['account_code']}</td><td>{$acc['account_name']}</td><td class='amount'>" . number_format($acc['amount_cents'] / 100, 2) . "</td></tr>";
        }
        foreach ($data['liabilities']['long_term'] as $acc) {
            $html .= "<tr><td>{$acc['account_code']}</td><td>{$acc['account_name']}</td><td class='amount'>" . number_format($acc['amount_cents'] / 100, 2) . "</td></tr>";
        }
        $html .= "<tr><td></td><td><strong>Total Liabilities</strong></td><td class='amount'><strong>" . number_format($data['liabilities']['total_cents'] / 100, 2) . "</strong></td></tr>";
        $html .= "</table>";

        $html .= "<h2>Equity</h2>";
        $html .= "<table><tr><th>Code</th><th>Account</th><th class='amount'>Amount</th></tr>";
        foreach ($data['equity']['accounts'] as $acc) {
            $html .= "<tr><td>{$acc['account_code']}</td><td>{$acc['account_name']}</td><td class='amount'>" . number_format($acc['amount_cents'] / 100, 2) . "</td></tr>";
        }
        $html .= "<tr><td></td><td>Retained Earnings</td><td class='amount'>" . number_format($data['equity']['retained_earnings_cents'] / 100, 2) . "</td></tr>";
        $html .= "<tr><td></td><td><strong>Total Equity</strong></td><td class='amount'><strong>" . number_format($data['equity']['total_cents'] / 100, 2) . "</strong></td></tr>";
        $html .= "</table>";

        $html .= "</body></html>";
        return $html;
    }
