// Transaction Page JavaScript - Complete Implementation
// Accounting System with Double-Entry Bookkeeping

console.log('[TRANSACTIONS] Starting...');

// Global state
let allAccounts = [];
let allTransactions = [];
let filteredTransactions = [];
let transactionLines = [];
let lineCounter = 0;
let currentPage = 1;
const itemsPerPage = 10;

// Account type codes
const ACCOUNT_TYPES = {
    1: 'Asset',
    2: 'Liability',
    3: 'Equity',
    4: 'Revenue',
    5: 'Expense'
};

// Code ranges for account types
const CODE_RANGES = {
    1: { start: 1000, end: 1999, name: 'Assets' },
    2: { start: 2000, end: 2999, name: 'Liabilities' },
    3: { start: 3000, end: 3999, name: 'Equity' },
    4: { start: 4000, end: 4999, name: 'Revenue' },
    5: { start: 5000, end: 5999, name: 'Expenses' }
};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('[TRANSACTIONS] DOM loaded, initializing...');

    // Load accounts and transactions
    loadAccounts();
    loadTransactions();

    // Setup logout
    document.getElementById('logoutBtn').addEventListener('click', () => {
        fetch('/php/api/auth/logout.php', { method: 'POST' })
            .then(() => window.location.href = '/tenant/login.html');
    });

    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    if (document.getElementById('transactionDate')) {
        document.getElementById('transactionDate').value = today;
    }
});

// ==================== ACCOUNT CREATION MODAL ====================

// Open account creation modal
function openAccountModal() {
    console.log('[TRANSACTIONS] Opening account creation modal...');

    // Reset form
    document.getElementById('accountForm').reset();
    document.getElementById('assetWarning').style.display = 'none';
    document.getElementById('otherTypeInfo').style.display = 'none';

    // Show modal
    document.getElementById('accountModal').style.display = 'flex';

    console.log('[TRANSACTIONS] ✅ Account modal opened');
}

// Close account modal
function closeAccountModal() {
    document.getElementById('accountModal').style.display = 'none';
}

// Handle account type change
function handleAccountTypeChange() {
    const typeSelect = document.getElementById('accountType');
    const codeInput = document.getElementById('accountCode');
    const assetWarning = document.getElementById('assetWarning');
    const otherTypeInfo = document.getElementById('otherTypeInfo');
    const typeId = parseInt(typeSelect.value);

    if (!typeId) {
        assetWarning.style.display = 'none';
        otherTypeInfo.style.display = 'none';
        return;
    }

    // Show asset warning if Asset type selected
    if (typeId === 1) {
        assetWarning.style.display = 'block';
        otherTypeInfo.style.display = 'none';
    } else {
        assetWarning.style.display = 'none';
        otherTypeInfo.style.display = 'block';
    }

    // Suggest next available code
    const range = CODE_RANGES[typeId];
    const existingCodes = allAccounts
        .filter(a => a.account_type_id === typeId)
        .map(a => parseInt(a.account_code))
        .sort((a, b) => a - b);

    let suggestedCode = range.start + 1; // Start from X001

    for (let code of existingCodes) {
        if (code === suggestedCode) {
            suggestedCode++;
        } else if (code > suggestedCode) {
            break;
        }
    }

    codeInput.value = suggestedCode;
    console.log(`[TRANSACTIONS] Suggested code for ${range.name}: ${suggestedCode}`);
}

// Handle external account toggle
function handleExternalAccountToggle() {
    const isExternal = document.getElementById('isExternalAccount').checked;
    const codeInput = document.getElementById('accountCode');
    const typeSelect = document.getElementById('accountType');
    const assetWarning = document.getElementById('assetWarning');
    const otherTypeInfo = document.getElementById('otherTypeInfo');

    if (isExternal) {
        // External account - change code to EXT-X format
        const typeId = parseInt(typeSelect.value);
        if (typeId) {
            const typeNames = {1: 'ASSET', 2: 'LIABILITY', 3: 'EQUITY', 4: 'REVENUE', 5: 'EXPENSE'};
            codeInput.value = `EXT-${typeNames[typeId]}`;
            codeInput.readOnly = true;
            codeInput.style.background = '#fff3cd';
        }
        // Hide normal warnings
        assetWarning.style.display = 'none';
        otherTypeInfo.style.display = 'none';
    } else {
        // Normal account - restore normal behavior
        codeInput.readOnly = false;
        codeInput.style.background = '';
        handleAccountTypeChange(); // Re-suggest normal code
    }
}

// Save account
async function saveAccount() {
    console.log('[TRANSACTIONS] Saving account...');

    const code = document.getElementById('accountCode').value.trim();
    const name = document.getElementById('accountName').value.trim();
    const typeId = document.getElementById('accountType').value;
    const description = document.getElementById('accountDescription').value.trim();
    const isExternal = document.getElementById('isExternalAccount').checked;

    if (!code || !name || !typeId) {
        Notify.error('Please fill in all required fields!');
        return;
    }

    // Skip code validation for external accounts (they use EXT-X format)
    if (!isExternal) {
        // Validate code is within range for normal accounts
        const range = CODE_RANGES[typeId];
        const codeNum = parseInt(code);
        if (codeNum < range.start || codeNum > range.end) {
            Notify.error(`Account code must be between ${range.start} and ${range.end} for ${ACCOUNT_TYPES[typeId]} accounts!`);
            return;
        }
    }

    // Check if code already exists
    if (allAccounts.some(a => a.account_code === code)) {
        Notify.error(`Account code ${code} already exists! Please choose a different code.`);
        return;
    }

    const accountData = {
        account_code: code,
        account_name: name,
        account_type_id: parseInt(typeId),
        description: description,
        opening_balance: isExternal ? 999999999 : 0, // External = unlimited, Normal = 0
        is_system_account: isExternal ? 1 : 0, // Mark as external if checkbox checked
        is_active: 1
    };

    console.log('[TRANSACTIONS] Account data:', accountData);

    try {
        const response = await fetch('/php/api/accounts/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(accountData)
        });

        const result = await response.json();

        if (result.success) {
            Notify.success(`Account "${name}" (${code}) created successfully!`);
            closeAccountModal();
            loadAccounts(); // Reload accounts list
        } else {
            Notify.error('Failed to create account', result.message || 'Unknown error');
        }
    } catch (error) {
        console.error('[TRANSACTIONS] Save account error:', error);
        Notify.error('Error creating account. Please try again.', error.message);
    }
}

// ==================== TRANSACTION MODAL ====================

// Open transaction modal (renamed from openCreateModal)
function openTransactionModal() {
    console.log('[TRANSACTIONS] Opening transaction modal...');

    // Check if user has any accounts with balance
    const accountsWithBalance = allAccounts.filter(a => parseFloat(a.current_balance) > 0);

    if (accountsWithBalance.length === 0) {
        Notify.warning(
            'No accounts with balance found!',
            'You should create accounts and add opening balances first. Consider:\n\n' +
            '1. Create a Cash account\n' +
            '2. Create an Owner\'s Capital account\n' +
            '3. Record an investment transaction\n\n' +
            'This will give your accounts starting balances.'
        );
        // Still allow creation but warn them
    }

    // Reset form
    document.getElementById('transactionForm').reset();
    document.getElementById('transactionId').value = '';
    document.getElementById('transactionModalTitle').textContent = '📝 Create Transaction (Double-Entry)';

    // Set default date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('transactionDate').value = today;

    // Clear transaction lines
    transactionLines = [];
    lineCounter = 0;
    document.getElementById('transactionLinesContainer').innerHTML = '';

    // Add two default lines
    addTransactionLine();
    addTransactionLine();

    // Show modal
    document.getElementById('transactionModal').style.display = 'flex';

    console.log('[TRANSACTIONS] ✅ Transaction modal opened');
}

// Keep old function name for compatibility
function openCreateModal() {
    openTransactionModal();
}
function loadAccounts() {
    console.log('[TRANSACTIONS] Loading accounts...');

    const timestamp = new Date().getTime();
    fetch(`/php/api/accounts/list.php?_=${timestamp}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                allAccounts = data.data || [];
                console.log('[TRANSACTIONS] ✅ Accounts loaded:', allAccounts.length);
            }
        })
        .catch(error => {
            console.error('[TRANSACTIONS] Error loading accounts:', error);
        });
}

// Load all transactions
function loadTransactions() {
    console.log('[TRANSACTIONS] Loading transactions...');

    const container = document.getElementById('transactionsContainer');
    container.innerHTML = '<tr><td colspan="6" class="loading-cell"><div class="loading-spinner"></div>Loading transactions...</td></tr>';

    fetch('/php/api/transactions/list.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                allTransactions = data.data || [];
                filteredTransactions = allTransactions;
                renderTransactions();
                console.log('[TRANSACTIONS] ✅ Transactions loaded:', allTransactions.length);
            } else {
                container.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 30px; color: #e74c3c;">Failed to load transactions</td></tr>';
            }
        })
        .catch(error => {
            console.error('[TRANSACTIONS] Error loading transactions:', error);
            container.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 30px; color: #e74c3c;">Error loading transactions</td></tr>';
        });
}

// Render transactions table
function renderTransactions() {
    const container = document.getElementById('transactionsContainer');
    const paginationControls = document.getElementById('paginationControls');

    if (filteredTransactions.length === 0) {
        container.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 30px;">No transactions found. <button class="btn btn-secondary" onclick="openTransactionModal()" style="margin-left: 10px;">Create First Transaction</button></td></tr>';
        paginationControls.style.display = 'none';
        return;
    }

    // Pagination logic
    const totalPages = Math.ceil(filteredTransactions.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, filteredTransactions.length);
    const pageTransactions = filteredTransactions.slice(startIndex, endIndex);

    let html = '';
    pageTransactions.forEach(txn => {
        const statusColors = {
            1: '#f39c12', // Pending
            2: '#27ae60', // Posted
            3: '#95a5a6', // Voided
            4: '#f39c12'  // Pending Approval
        };

        const statusNames = {
            1: 'Pending',
            2: 'Posted',
            3: 'Voided',
            4: 'Pending Approval'
        };

        html += `
            <tr onclick="viewTransaction(${txn.id})" style="cursor: pointer;" title="Click to view details">
                <td><code style="background: #ecf0f1; padding: 4px 8px; border-radius: 3px;">${txn.transaction_number}</code></td>
                <td>${txn.transaction_date}</td>
                <td>${txn.description}</td>
                <td style="text-align: right;"><strong>$${parseFloat(txn.total_amount || 0).toFixed(2)}</strong></td>
                <td style="text-align: center;">
                    <span style="background: ${statusColors[txn.status_id]}; color: white; padding: 4px 12px; border-radius: 3px; font-size: 11px; font-weight: 600;">
                        ${statusNames[txn.status_id]}
                    </span>
                </td>
                <td style="text-align: center;" onclick="event.stopPropagation()">
                    ${txn.requires_approval == 1 ? `
                        <span style="color: #e67e22; font-size: 13px; font-weight: 600;">Admin Approval Needed</span>
                    ` : txn.status_id == 1 ? `
                        <button class="btn btn-secondary" onclick="editTransaction(${txn.id})" style="padding: 6px 12px; font-size: 12px; margin-right: 5px; background: #3498db; color: white;">Edit</button>
                        <button class="btn btn-secondary" onclick="postTransaction(${txn.id})" style="padding: 6px 12px; font-size: 12px; margin-right: 5px; background: #27ae60; color: white;">Post</button>
                        <button class="btn btn-secondary" onclick="deleteTransaction(${txn.id}, '${txn.transaction_number}')" style="padding: 6px 12px; font-size: 12px; background: #e74c3c; color: white;">Delete</button>
                    ` : ''}
                </td>
            </tr>
        `;
    });

    container.innerHTML = html;

    // Update pagination controls
    paginationControls.style.display = 'flex';
    document.getElementById('showingStart').textContent = startIndex + 1;
    document.getElementById('showingEnd').textContent = endIndex;
    document.getElementById('showingTotal').textContent = filteredTransactions.length;
    document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;

    // Enable/disable buttons
    document.getElementById('prevBtn').disabled = currentPage === 1;
    document.getElementById('nextBtn').disabled = currentPage === totalPages;
}

// Pagination functions
function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        renderTransactions();
    }
}

function nextPage() {
    const totalPages = Math.ceil(filteredTransactions.length / itemsPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        renderTransactions();
    }
}

// Filter transactions
function filterTransactions() {
    const statusFilter = document.getElementById('filterStatus').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();

    filteredTransactions = allTransactions.filter(txn => {
        if (statusFilter && txn.status_id != statusFilter) return false;
        if (dateFrom && txn.transaction_date < dateFrom) return false;
        if (dateTo && txn.transaction_date > dateTo) return false;
        if (searchTerm) {
            const searchable = (txn.transaction_number + txn.description).toLowerCase();
            if (!searchable.includes(searchTerm)) return false;
        }
        return true;
    });

    currentPage = 1; // Reset to first page
    renderTransactions();
}

// Open create modal
function openCreateModal() {
    console.log('[TRANSACTIONS] Opening create modal...');

    // Reset form
    document.getElementById('transactionForm').reset();
    document.getElementById('transactionId').value = '';
    document.getElementById('transactionModalTitle').textContent = 'Create Transaction';

    // Set default date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('transactionDate').value = today;

    // Clear transaction lines
    transactionLines = [];
    lineCounter = 0;
    document.getElementById('transactionLinesContainer').innerHTML = '';

    // Add two default lines
    addTransactionLine();
    addTransactionLine();

    // Show modal
    document.getElementById('transactionModal').style.display = 'flex';

    console.log('[TRANSACTIONS] ✅ Create modal opened');
}

// Close transaction modal
function closeTransactionModal() {
    document.getElementById('transactionModal').style.display = 'none';
}

// Add transaction line
function addTransactionLine() {
    const lineId = lineCounter++;
    const container = document.getElementById('transactionLinesContainer');

    const lineHtml = `
        <div class="transaction-line" id="line-${lineId}">
            <div class="transaction-line__header">
                <span class="transaction-line__number">Line ${lineId + 1}</span>
                <button type="button" class="transaction-line__remove" onclick="removeTransactionLine(${lineId})">Remove</button>
            </div>
            <div class="transaction-line__fields">
                <div class="form-group">
                    <label>Account <span style="color: red;">*</span></label>
                    <select class="form-control" id="account-${lineId}" onchange="updateAccountAvailability(); updateLineHelp(${lineId}); calculateBalance();" required>
                        <option value="">-- Select Account --</option>
                        ${renderAccountOptions()}
                    </select>
                    <small style="color: #7f8c8d; font-size: 11px;">💡 To create new accounts, use the "Create Account" button above the transaction form.</small>
                </div>
                <div class="form-group">
                    <label>Type <span style="color: red;">*</span></label>
                    <select class="form-control" id="type-${lineId}" onchange="updateLineHelp(${lineId}); calculateBalance();" required style="font-family: monospace; font-weight: bold;">
                        <option value="">-- Select Type --</option>
                        <option value="debit">⬅️ DEBIT (Receiving/Increasing)</option>
                        <option value="credit">➡️ CREDIT (Giving/Source)</option>
                    </select>
                    <div id="help-${lineId}" style="margin-top: 5px; padding: 8px; border-radius: 4px; font-size: 12px; display: none;"></div>
                </div>
                <div class="form-group">
                    <label>Amount <span style="color: red;">*</span></label>
                    <input type="number" class="form-control" id="amount-${lineId}" step="0.01" min="0" placeholder="0.00" onchange="calculateBalance()" required>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', lineHtml);
    transactionLines.push({ id: lineId, isNew: false });
    updateAccountAvailability(); // Update dropdowns
}

// Update account availability across all lines
function updateAccountAvailability() {
    // Get all selected account IDs
    const selectedAccounts = new Set();
    transactionLines.forEach(line => {
        const select = document.getElementById(`account-${line.id}`);
        if (select && select.value) {
            selectedAccounts.add(select.value);
        }
    });

    // Update each line's dropdown
    transactionLines.forEach(line => {
        const select = document.getElementById(`account-${line.id}`);
        if (!select) return;

        const currentValue = select.value;

        // Disable options that are selected in OTHER lines
        Array.from(select.options).forEach(option => {
            if (option.value === '') return; // Skip empty option

            if (selectedAccounts.has(option.value) && option.value !== currentValue) {
                option.disabled = true;
                option.style.color = '#95a5a6';
                option.textContent = option.textContent.replace(' (already used)', '') + ' (already used)';
            } else {
                option.disabled = false;
                option.style.color = '';
                option.textContent = option.textContent.replace(' (already used)', '');
            }
        });
    });
}

// Render account options grouped by type
function renderAccountOptions() {
    const grouped = {};
    const externalAccounts = [];

    // Separate external source accounts from regular accounts
    allAccounts.forEach(account => {
        if (account.is_system_account) {
            externalAccounts.push(account);
        } else {
            if (!grouped[account.account_type_id]) {
                grouped[account.account_type_id] = [];
            }
            grouped[account.account_type_id].push(account);
        }
    });

    let html = '';

    // First, render external source accounts if any exist
    if (externalAccounts.length > 0) {
        html += `<optgroup label="🌐 EXTERNAL SOURCES (Outside World)" style="background: #e3f2fd;">`;
        externalAccounts.forEach(account => {
            const badge = account.account_code === 'SYS-EXT-EQUITY' ? '💰 Capital' :
                         account.account_code === 'SYS-EXT-LIAB' ? '🏦 Loans' : '👥 Revenue';
            html += `<option value="${account.id}">${badge} ${account.account_name}</option>`;
        });
        html += `</optgroup>`;
    }

    // Then render regular accounts in order: Assets, Liabilities, Equity, Revenue, Expenses
    [1, 2, 3, 4, 5].forEach(typeId => {
        if (grouped[typeId] && grouped[typeId].length > 0) {
            html += `<optgroup label="💼 YOUR ${ACCOUNT_TYPES[typeId].toUpperCase()} ACCOUNTS">`;
            grouped[typeId].forEach(account => {
                const balanceNum = parseFloat(account.current_balance) || 0;
                const balance = balanceNum >= 0 ? `$${balanceNum.toFixed(2)}` : `($${Math.abs(balanceNum).toFixed(2)})`;
                html += `<option value="${account.id}">${account.account_code} - ${account.account_name} [${balance}]</option>`;
            });
            html += `</optgroup>`;
        }
    });

    return html;
}


// Remove transaction line
function removeTransactionLine(lineId) {
    const lineElement = document.getElementById(`line-${lineId}`);
    if (lineElement) {
        lineElement.remove();
    }
    transactionLines = transactionLines.filter(l => l.id !== lineId);
    updateAccountAvailability(); // Update dropdowns
    calculateBalance();
}

// Update line help text based on account and type selection
function updateLineHelp(lineId) {
    const accountSelect = document.getElementById(`account-${lineId}`);
    const typeSelect = document.getElementById(`type-${lineId}`);
    const helpDiv = document.getElementById(`help-${lineId}`);

    if (!accountSelect || !typeSelect || !helpDiv) return;

    const accountId = accountSelect.value;
    const type = typeSelect.value;

    if (!accountId || !type) {
        helpDiv.style.display = 'none';
        return;
    }

    // Find account details
    const account = allAccounts.find(a => a.id == accountId);
    if (!account) {
        helpDiv.style.display = 'none';
        return;
    }

    const accountType = account.account_type_name || '';
    const isSystemAccount = account.is_system_account;

    let message = '';
    let bgColor = '';
    let textColor = '#333';

    if (isSystemAccount) {
        // External source accounts
        if (type === 'debit') {
            message = '⚠️ Note: External sources typically receive CREDITS. Debiting indicates money leaving the external entity. Please verify this is intentional.';
            bgColor = '#fff3cd';
            textColor = '#856404';
        } else {
            message = '✅ Correct: CREDIT external source when capital flows FROM external entity INTO your company accounts.';
            bgColor = '#d4edda';
            textColor = '#155724';
        }
    } else {
        // Regular accounts
        if (accountType === 'Asset') {
            if (type === 'debit') {
                message = '✅ This transaction will INCREASE the asset balance (e.g., receiving cash, acquiring equipment).';
                bgColor = '#d4edda';
                textColor = '#155724';
            } else {
                message = '→ This transaction will DECREASE the asset balance (e.g., disbursing cash, disposing of equipment).';
                bgColor = '#d1ecf1';
                textColor = '#0c5460';
            }
        } else if (accountType === 'Liability') {
            if (type === 'debit') {
                message = '→ This transaction will DECREASE the liability balance (e.g., paying off debt obligations).';
                bgColor = '#d1ecf1';
                textColor = '#0c5460';
            } else {
                message = '✅ This transaction will INCREASE the liability balance (e.g., incurring new debt, accepting credit terms).';
                bgColor = '#d4edda';
                textColor = '#155724';
            }
        } else if (accountType === 'Equity') {
            if (type === 'debit') {
                message = '→ This transaction will DECREASE the equity balance (e.g., owner distributions, net losses).';
                bgColor = '#d1ecf1';
                textColor = '#0c5460';
            } else {
                message = '✅ This transaction will INCREASE the equity balance (e.g., capital contributions, net income).';
                bgColor = '#d4edda';
                textColor = '#155724';
            }
        } else if (accountType === 'Revenue') {
            if (type === 'debit') {
                message = '→ This transaction will DECREASE revenue (e.g., sales returns, revenue adjustments). Uncommon operation.';
                bgColor = '#d1ecf1';
                textColor = '#0c5460';
            } else {
                message = '✅ This transaction will INCREASE revenue (e.g., recognizing sales, service income).';
                bgColor = '#d4edda';
                textColor = '#155724';
            }
        } else if (accountType === 'Expense') {
            if (type === 'debit') {
                message = '✅ This transaction will INCREASE expenses (e.g., recording rent, salaries, operational costs).';
                bgColor = '#d4edda';
                textColor = '#155724';
            } else {
                message = '→ This transaction will DECREASE expenses (e.g., expense reversals, corrections). Uncommon operation.';
                bgColor = '#d1ecf1';
                textColor = '#0c5460';
            }
        }
    }

    helpDiv.textContent = message;
    helpDiv.style.backgroundColor = bgColor;
    helpDiv.style.color = textColor;
    helpDiv.style.display = 'block';
    helpDiv.style.border = `1px solid ${textColor}`;
}

// Calculate balance
function calculateBalance() {
    let totalDebits = 0;
    let totalCredits = 0;

    transactionLines.forEach(line => {
        const type = document.getElementById(`type-${line.id}`)?.value;
        const amount = parseFloat(document.getElementById(`amount-${line.id}`)?.value || 0);

        if (type === 'debit') {
            totalDebits += amount;
        } else if (type === 'credit') {
            totalCredits += amount;
        }
    });

    const difference = totalDebits - totalCredits;

    // Update display
    document.getElementById('totalDebits').textContent = '$' + totalDebits.toFixed(2);
    document.getElementById('totalCredits').textContent = '$' + totalCredits.toFixed(2);
    document.getElementById('balanceDifference').textContent = '$' + Math.abs(difference).toFixed(2);

    const balanceMessage = document.getElementById('balanceMessage');
    const differenceElement = document.getElementById('balanceDifference');

    if (difference === 0 && totalDebits > 0) {
        differenceElement.style.color = '#27ae60';
        balanceMessage.className = 'balance-message success';
        balanceMessage.textContent = '✅ Transaction is balanced! Ready to save.';
        balanceMessage.style.display = 'block';
        return true;
    } else if (difference !== 0 && (totalDebits > 0 || totalCredits > 0)) {
        differenceElement.style.color = '#e74c3c';
        balanceMessage.className = 'balance-message error';
        balanceMessage.textContent = '❌ Transaction is NOT balanced! Debits must equal Credits.';
        balanceMessage.style.display = 'block';
        return false;
    } else {
        balanceMessage.style.display = 'none';
        differenceElement.style.color = '#95a5a6';
        return false;
    }
}

// Validate if transaction would violate accounting integrity rules
function validateAccountingIntegrity(lines) {
    console.log('[TRANSACTIONS] Validating accounting integrity...');

    const violations = [];
    const adminApprovalNeeded = [];

    // Account type rules: 1=Asset, 2=Liability, 3=Equity, 4=Revenue, 5=Expense
    const cannotBeNegative = [1, 2, 4, 5]; // Asset, Liability, Revenue, Expense - MUST NOT go negative
    const accountTypeNames = {
        1: 'Asset',
        2: 'Liability',
        3: 'Equity',
        4: 'Revenue',
        5: 'Expense'
    };

    // NEW: Check for Revenue/Expense pairing violations
    const accountTypesInTransaction = new Set();
    const hasRevenue = lines.some(line => {
        const account = allAccounts.find(a => a.id === line.account_id);
        return account && account.account_type_id === 4;
    });
    const hasExpense = lines.some(line => {
        const account = allAccounts.find(a => a.id === line.account_id);
        return account && account.account_type_id === 5;
    });
    const hasEquity = lines.some(line => {
        const account = allAccounts.find(a => a.id === line.account_id);
        return account && account.account_type_id === 3;
    });

    // ❌ CRITICAL VIOLATION: Revenue ↔ Expense pairing
    if (hasRevenue && hasExpense) {
        violations.push({
            account: 'Transaction Structure',
            type: 'Revenue ↔ Expense',
            rule: 'Revenue and Expense accounts cannot be used together in the same transaction',
            explanation: 'Revenue and Expenses are LABELS that describe why money moved, not actual money. They should never directly offset each other. Revenue pairs with Assets/Liabilities (money coming in), Expenses pair with Assets/Liabilities (money going out).',
            severity: 'CRITICAL',
            suggestion: 'Split this into separate transactions: one for revenue (e.g., Cash ← Sales Revenue), another for expense (e.g., Rent Expense → Cash)',
            learnMore: '/docs/accounting-rules.html#revenue-expense'
        });
    }

    // ⚠️ ADMIN APPROVAL: Revenue/Expense ↔ Equity (Closing Entry)
    if ((hasRevenue || hasExpense) && hasEquity) {
        const revenueExpenseAccounts = lines
            .filter(line => {
                const account = allAccounts.find(a => a.id === line.account_id);
                return account && (account.account_type_id === 4 || account.account_type_id === 5);
            })
            .map(line => {
                const account = allAccounts.find(a => a.id === line.account_id);
                return account.account_name;
            });

        adminApprovalNeeded.push({
            account: 'Closing Entry Detected',
            type: hasRevenue ? 'Revenue → Equity' : 'Expense → Equity',
            rule: 'Revenue/Expense accounts can only interact with Equity during period-end closing entries',
            explanation: `You're transferring ${hasRevenue ? 'revenue' : 'expenses'} to equity. This is ONLY done at the end of an accounting period to close temporary accounts and update Retained Earnings with net income/loss.`,
            accounts: revenueExpenseAccounts.join(', '),
            severity: 'CLOSING_ENTRY',
            suggestion: 'If this is NOT a period-end closing entry, you should instead pair Revenue/Expenses with Asset or Liability accounts (e.g., Cash ← Sales Revenue, Rent Expense → Cash).',
            learnMore: '/docs/accounting-rules.html#closing-entries'
        });
    }

    for (let line of lines) {
        const account = allAccounts.find(a => a.id === line.account_id);

        if (!account) continue;

        // Calculate the change this line would make based on account type and debit/credit
        // For Assets & Expenses: Debit increases (+), Credit decreases (-)
        // For Liabilities, Equity, Revenue: Credit increases (+), Debit decreases (-)
        let change;
        const isDebit = (line.line_type === 'debit');

        if (account.account_type_id === 1 || account.account_type_id === 5) {
            // Asset or Expense: Debit increases, Credit decreases
            change = isDebit ? line.amount : -line.amount;
        } else {
            // Liability, Equity, or Revenue: Credit increases, Debit decreases
            change = isDebit ? -line.amount : line.amount;
        }

        const newBalance = parseFloat(account.current_balance) + change;

        // Check Asset, Liability, Revenue, Expense - MUST NOT go negative
        if (cannotBeNegative.includes(account.account_type_id) && newBalance < 0) {
            violations.push({
                account: account.account_name,
                type: accountTypeNames[account.account_type_id],
                currentBalance: parseFloat(account.current_balance),
                change: change,
                newBalance: newBalance,
                rule: `${accountTypeNames[account.account_type_id]} accounts cannot have negative balances`,
                severity: 'CRITICAL'
            });
        }

        // Check Equity - CAN go negative, but requires admin approval for negative balance
        // Positive equity = Normal (owner invested money)
        // Negative equity = Rare (owner withdrew more than invested - owner owes company)
        if (account.account_type_id === 3 && newBalance < 0 && !hasRevenue && !hasExpense) {
            adminApprovalNeeded.push({
                account: account.account_name,
                type: 'Equity',
                currentBalance: parseFloat(account.current_balance),
                change: change,
                newBalance: newBalance,
                rule: 'Negative equity (owner withdrew more than invested) requires admin approval',
                severity: 'ADMIN_APPROVAL_REQUIRED'
            });
        }
    }

    return {
        violations: violations,
        adminApprovalNeeded: adminApprovalNeeded,
        hasBlockingViolations: violations.length > 0,
        needsAdminApproval: adminApprovalNeeded.length > 0
    };
}

// Show admin approval request modal for rare scenarios (negative equity or closing entries)
function showAdminApprovalRequest(adminApprovalCases, onApprove) {
    const isClosingEntry = adminApprovalCases.some(c => c.severity === 'CLOSING_ENTRY');

    const casesList = adminApprovalCases.map(c => {
        // Handle closing entry cases differently
        if (c.severity === 'CLOSING_ENTRY') {
            return `
                <div style="margin-bottom: 15px; padding: 15px; background: #fff9e6; border-left: 4px solid #f39c12; border-radius: 4px;">
                    <strong style="color: #856404;">⚠️ ${c.type}</strong>
                    <div style="margin-top: 8px; font-size: 13px; color: #666;">
                        <div><strong>Accounts:</strong> ${c.accounts}</div>
                        <div style="margin-top: 8px; font-style: italic; color: #856404;">
                            📖 ${c.rule}
                        </div>
                        <div style="margin-top: 8px; background: rgba(255,255,255,0.7); padding: 8px; border-radius: 4px; color: #666;">
                            ${c.explanation}
                        </div>
                        ${c.suggestion ? `
                            <div style="margin-top: 8px; background: #e3f2fd; padding: 8px; border-radius: 4px; color: #1565c0; font-size: 12px;">
                                💡 <strong>Suggestion:</strong> ${c.suggestion}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Handle negative equity cases
        return `
            <div style="margin-bottom: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #f39c12; border-radius: 4px;">
                <strong style="color: #856404;">⚠️ ${c.type}: ${c.account}</strong>
                <div style="margin-top: 8px; font-size: 13px; color: #666;">
                    <div>Current Balance: <strong>$${c.currentBalance.toFixed(2)}</strong></div>
                    <div>Change: <strong>${c.change >= 0 ? '+' : ''}$${c.change.toFixed(2)}</strong></div>
                    <div style="color: #f39c12; font-weight: 600;">Would Result In: $${c.newBalance.toFixed(2)} ⚠️</div>
                    <div style="margin-top: 8px; font-style: italic; color: #856404;">
                        📖 ${c.rule}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    const overlay = document.createElement('div');
    overlay.className = 'notification-overlay';
    overlay.style.zIndex = '99999';

    overlay.innerHTML = `
        <div class="notification-box" style="max-width: 700px;">
            <div class="notification-header warning">
                <div class="notification-icon">⚠️</div>
                <h3 class="notification-title">Admin Approval Required</h3>
            </div>
            <div class="notification-body">
                <div style="background: #fff3cd; border: 2px solid #f39c12; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <strong style="color: #856404;">⚠️ RARE SCENARIO DETECTED</strong>
                    <p style="margin: 10px 0 0 0; color: #856404; font-size: 14px;">
                        This transaction would create a scenario that, while <strong>valid</strong>, requires administrator review:
                    </p>
                </div>
                
                ${casesList}
                
                <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; border-radius: 4px; margin-top: 20px;">
                    <strong style="color: #1565c0;">📚 What This Means:</strong>
                    <ul style="margin: 10px 0 0 20px; color: #1976d2; font-size: 13px;">
                        <li><strong>Negative Equity</strong> means the owner has withdrawn more money than they invested</li>
                        <li>This means the <strong>owner now owes money back to the company</strong></li>
                        <li>This is <strong>rare but valid</strong> in accounting principles</li>
                        <li>Due to its seriousness, <strong>admin approval is required</strong> to proceed</li>
                    </ul>
                </div>
                
                <div style="background: #fff9e6; border: 2px solid #f39c12; padding: 15px; border-radius: 4px; margin-top: 20px;">
                    <strong style="color: #856404;">🔐 What Happens Next:</strong>
                    <ol style="margin: 10px 0 0 20px; color: #856404; font-size: 13px; line-height: 1.8;">
                        <li>Transaction will be saved as <strong>"Pending Admin Approval"</strong></li>
                        <li>Admin will be notified to review this transaction</li>
                        <li>Admin can <strong>approve</strong> (transaction posts) or <strong>decline</strong> (with reason)</li>
                        <li>You'll be notified of the decision</li>
                    </ol>
                </div>
                
                <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin-top: 20px; text-align: center;">
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        📖 For more information, see: 
                        <a href="/docs/accounting-rules.html#equity" style="color: #3498db; text-decoration: none; font-weight: 600;">
                            Equity Account Rules
                        </a>
                    </p>
                </div>
            </div>
            <div class="notification-footer" style="justify-content: space-between;">
                <button class="notification-btn notification-btn-secondary" onclick="this.closest('.notification-overlay').remove()">
                    Cancel
                </button>
                <button class="notification-btn notification-btn-primary" id="submitForReviewBtn">
                    Submit for Admin Review →
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Handle submit for review
    document.getElementById('submitForReviewBtn').onclick = () => {
        overlay.remove();
        onApprove(); // Call the callback to save as pending approval
    };

    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.remove();
        }
    });

    // Close on Escape
    const escapeHandler = (e) => {
        if (e.key === 'Escape' && overlay.parentElement) {
            overlay.remove();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

// Show professional integrity warning modal
function showIntegrityWarning(violations) {
    const violationsList = violations.map(v => {
        // Handle Revenue ↔ Expense violation specially
        if (v.type === 'Revenue ↔ Expense') {
            return `
                <div style="margin-bottom: 20px; padding: 20px; background: #ffebee; border: 3px solid #ef5350; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <span style="font-size: 32px;">❌</span>
                        <strong style="color: #c62828; font-size: 16px;">${v.type}: ${v.account}</strong>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-top: 12px;">
                        <div style="color: #d32f2f; font-weight: 600; margin-bottom: 8px;">🚫 ${v.rule}</div>
                        <div style="color: #666; font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
                            ${v.explanation}
                        </div>
                        ${v.suggestion ? `
                            <div style="background: #e3f2fd; padding: 12px; border-radius: 4px; border-left: 4px solid #2196f3;">
                                <div style="color: #1565c0; font-weight: 600; margin-bottom: 6px;">💡 How to Fix:</div>
                                <div style="color: #1976d2; font-size: 13px;">${v.suggestion}</div>
                            </div>
                        ` : ''}
                        ${v.learnMore ? `
                            <div style="margin-top: 12px; text-align: center;">
                                <a href="${v.learnMore}" target="_blank" style="color: #2196f3; text-decoration: none; font-weight: 600; font-size: 13px;">
                                    📚 Learn More: Accounting Rules →
                                </a>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Handle negative balance violations
        return `
            <div style="margin-bottom: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #f39c12; border-radius: 4px;">
                <strong style="color: #856404;">⚠️ ${v.type}: ${v.account}</strong>
                <div style="margin-top: 8px; font-size: 13px; color: #666;">
                    <div>Current Balance: <strong>$${v.currentBalance.toFixed(2)}</strong></div>
                    <div>Change: <strong>${v.change >= 0 ? '+' : ''}$${v.change.toFixed(2)}</strong></div>
                    <div style="color: #c0392b; font-weight: 600;">Would Result In: $${v.newBalance.toFixed(2)} ❌</div>
                    <div style="margin-top: 8px; font-style: italic; color: #856404;">
                        📖 Rule: ${v.rule}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    const overlay = document.createElement('div');
    overlay.className = 'notification-overlay';
    overlay.style.zIndex = '99999';

    overlay.innerHTML = `
        <div class="notification-box" style="max-width: 700px;">
            <div class="notification-header error">
                <div class="notification-icon">🚨</div>
                <h3 class="notification-title">Transaction Violates Financial Integrity Rules</h3>
            </div>
            <div class="notification-body">
                <div style="background: #ffebee; border: 2px solid #ef5350; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <strong style="color: #c62828;">⛔ CRITICAL: This transaction would break the accounting system!</strong>
                    <p style="margin: 10px 0 0 0; color: #d32f2f; font-size: 14px;">
                        The following accounts would have invalid balances that violate double-entry bookkeeping principles:
                    </p>
                </div>
                
                ${violationsList}
                
                <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; border-radius: 4px; margin-top: 20px;">
                    <strong style="color: #1565c0;">📚 Accounting Principles:</strong>
                    <ul style="margin: 10px 0 0 20px; color: #1976d2; font-size: 13px;">
                        <li><strong>Assets</strong> represent what you OWN - Cannot own negative money!</li>
                        <li><strong>Liabilities</strong> represent what you OWE - Cannot owe negative debt!</li>
                        <li><strong>Revenue</strong> represents what you EARN - Cannot have negative sales!</li>
                        <li><strong>Expenses</strong> represent what you SPEND - Cannot have negative costs!</li>
                        <li><strong>Equity</strong> can go negative (owner withdrew more than invested)</li>
                    </ul>
                </div>
                
                <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin-top: 20px; text-align: center;">
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        📖 For more information, see: 
                        <a href="/docs/accounting-rules.html" style="color: #3498db; text-decoration: none; font-weight: 600;">
                            System Accounting Rules & Documentation
                        </a>
                    </p>
                </div>
            </div>
            <div class="notification-footer">
                <button class="notification-btn notification-btn-primary" onclick="this.closest('.notification-overlay').remove()">
                    I Understand - Let Me Fix This
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.remove();
        }
    });

    // Close on Escape
    const escapeHandler = (e) => {
        if (e.key === 'Escape' && overlay.parentElement) {
            overlay.remove();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

// Save transaction (pending or posted)
async function saveTransaction(status) {
    console.log(`[TRANSACTIONS] Saving transaction as ${status}...`);

    // Validate balance
    if (!calculateBalance()) {
        Notify.error('Cannot save unbalanced transaction!', 'Debits must equal Credits.');
        return;
    }

    // Gather form data
    const transactionDate = document.getElementById('transactionDate').value;
    const description = document.getElementById('description').value;
    const referenceNumber = document.getElementById('referenceNumber').value;

    if (!transactionDate || !description) {
        Notify.error('Please fill in all required fields!');
        return;
    }

    // Gather transaction lines
    const lines = [];
    const usedAccounts = new Set(); // Track which accounts are already used

    for (let line of transactionLines) {
        const accountSelect = document.getElementById(`account-${line.id}`);
        const type = document.getElementById(`type-${line.id}`).value;
        const amount = document.getElementById(`amount-${line.id}`).value;

        if (!accountSelect.value || !type || !amount) {
            Notify.error(`Please complete all fields in Line ${line.id + 1}`);
            return;
        }

        const accountId = parseInt(accountSelect.value);

        // Check if this account is already used
        if (usedAccounts.has(accountId)) {
            const accountName = accountSelect.options[accountSelect.selectedIndex].text;
            Notify.error(
                `Cannot use the same account twice!`,
                `Account "${accountName}" is already used in this transaction. You cannot debit and credit the same account.`
            );
            return;
        }

        usedAccounts.add(accountId);

        lines.push({
            account_id: accountId,
            line_type: type,
            amount: parseFloat(amount)
        });
    }

    // Prepare transaction data BEFORE validation (needed for admin approval callback)
    const transactionData = {
        transaction_date: transactionDate,
        description: description,
        reference_number: referenceNumber || null,
        status: status, // 'pending' or 'posted'
        lines: lines
    };

    // ⚠️ CRITICAL: Validate accounting integrity BEFORE saving
    // This prevents transactions that would violate fundamental accounting rules
    const validation = validateAccountingIntegrity(lines);

    // CRITICAL VIOLATIONS: Block transaction completely
    if (validation.hasBlockingViolations) {
        console.log('[TRANSACTIONS] ❌ Critical violations detected:', validation.violations);
        showIntegrityWarning(validation.violations);
        return; // Stop the transaction from being saved
    }

    // ADMIN APPROVAL NEEDED: Allow but require admin review
    if (validation.needsAdminApproval && status === 'posted') {
        console.log('[TRANSACTIONS] ⚠️ Admin approval required:', validation.adminApprovalNeeded);
        showAdminApprovalRequest(validation.adminApprovalNeeded, () => {
            // User confirmed - save as pending admin approval with requires_approval = TRUE
            console.log('[TRANSACTIONS] User confirmed admin approval submission');
            saveTransactionToBackend(transactionData, 'pending_approval');
        });
        return; // Show approval modal first
    }


    console.log('[TRANSACTIONS] Transaction data:', transactionData);

    // Save to backend
    await saveTransactionToBackend(transactionData, status);
}

// Separate function to save transaction to backend
async function saveTransactionToBackend(transactionData, status) {
    // Show loading
    const savePendingBtn = document.getElementById('savePendingBtn');
    const savePostedBtn = document.getElementById('savePostedBtn');
    const originalPendingText = savePendingBtn?.textContent || 'Save as Pending';
    const originalPostedText = savePostedBtn?.textContent || 'Save & Post';

    if (savePendingBtn) {
        savePendingBtn.textContent = 'Saving...';
        savePendingBtn.disabled = true;
    }
    if (savePostedBtn) {
        savePostedBtn.textContent = 'Saving...';
        savePostedBtn.disabled = true;
    }

    try {
        // Check if editing or creating
        const transactionId = document.getElementById('transactionId').value;
        const isEditing = transactionId && transactionId !== '';

        // Override status if pending_approval and set requires_approval flag
        const finalData = {
            ...transactionData,
            status: status === 'pending_approval' ? 'pending' : status,
            requires_approval: status === 'pending_approval' ? true : false
        };

        // Debug log to verify requires_approval is set correctly
        console.log('[TRANSACTIONS] Final data being sent:', {
            status: finalData.status,
            requires_approval: finalData.requires_approval,
            isApprovalMode: status === 'pending_approval'
        });

        // Add ID if editing
        if (isEditing) {
            finalData.id = parseInt(transactionId);
        }

        // Choose API endpoint
        const apiUrl = isEditing
            ? '/php/api/transactions/update.php'
            : '/php/api/transactions/create.php';

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(finalData)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const resultText = await response.text();
        try {
            const result = JSON.parse(resultText);
            if (result.success) {
                if (status === 'pending_approval') {
                    Notify.success(
                        'Transaction submitted for admin approval!',
                        'An administrator will review this transaction. You will be notified of the decision.'
                    );
                } else {
                    const action = isEditing ? 'updated' : (status === 'posted' ? 'posted' : 'saved as pending');
                    Notify.success(`Transaction ${action} successfully!`);
                }
                closeTransactionModal();
                loadAccounts(); // Reload accounts in case new ones were created
                loadTransactions(); // Reload transactions
            } else {
                Notify.error('Failed to save transaction', result.message || 'Unknown error');
            }
        } catch (e) {
            console.error('Error parsing JSON:', e);
            console.error('Received text:', resultText);
            Notify.error('Error saving transaction. Please try again.', 'Invalid response from server.');
        }
    } catch (error) {
        console.error('[TRANSACTIONS] Save error:', error);
        Notify.error('Error saving transaction. Please try again.', error.message);
    } finally {
        if (savePendingBtn) {
            savePendingBtn.textContent = originalPendingText;
            savePendingBtn.disabled = false;
        }
        if (savePostedBtn) {
            savePostedBtn.textContent = originalPostedText;
            savePostedBtn.disabled = false;
        }
    }
}

// View transaction details
async function viewTransaction(id) {
    console.log('[TRANSACTIONS] Viewing transaction #' + id);

    // Show modal with loading state
    const modal = document.getElementById('viewModal');
    const modalContent = document.getElementById('viewModalContent');

    modal.style.display = 'flex';
    modalContent.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="loading-spinner" style="display: inline-block; width: 40px; height: 40px; border: 4px solid rgba(52, 152, 219, 0.2); border-top-color: #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <p style="margin-top: 15px; color: #3498db;">Loading transaction details...</p>
        </div>
    `;

    try {
        const response = await fetch(`/php/api/transactions/get.php?id=${id}`);
        const result = await response.json();

        if (result.success) {
            showTransactionViewModal(result.data);
        } else {
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #e74c3c;">
                    <strong>Error loading transaction:</strong><br>
                    ${result.message}
                </div>
            `;
        }
    } catch (error) {
        console.error('[TRANSACTIONS] View error:', error);
        modalContent.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #e74c3c;">
                <strong>Error:</strong><br>
                ${error.message}
            </div>
        `;
    }
}

// Show transaction view modal with enhanced design
function showTransactionViewModal(transaction) {
    const modalContent = document.getElementById('viewModalContent');

    const statusNames = {
        1: 'Pending',
        2: 'Posted',
        3: 'Voided',
        4: 'Pending Approval'
    };

    const statusColors = {
        1: '#f39c12',
        2: '#27ae60',
        3: '#95a5a6',
        4: '#f39c12'
    };

    const canEdit = transaction.status_id == 1; // Only pending can be edited
    const isPendingApproval = transaction.status_id == 4;

    const totalDebits = transaction.lines
        .filter(l => l.line_type === 'debit')
        .reduce((sum, l) => sum + parseFloat(l.amount), 0);
    const totalCredits = transaction.lines
        .filter(l => l.line_type === 'credit')
        .reduce((sum, l) => sum + parseFloat(l.amount), 0);

    modalContent.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <strong style="color: #7f8c8d; font-size: 12px;">Transaction Number:</strong><br>
                <span style="font-family: monospace; color: #2c3e50; font-weight: 600;">${transaction.transaction_number}</span>
            </div>
            <div>
                <strong style="color: #7f8c8d; font-size: 12px;">Date:</strong><br>
                <span style="color: #2c3e50;">${transaction.transaction_date}</span>
            </div>
            <div>
                <strong style="color: #7f8c8d; font-size: 12px;">Status:</strong><br>
                <span style="background: ${statusColors[transaction.status_id]}; color: white; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                    ${statusNames[transaction.status_id]}
                </span>
            </div>
            <div>
                <strong style="color: #7f8c8d; font-size: 12px;">Total Amount:</strong><br>
                <span style="color: #3498db; font-size: 18px; font-weight: 700;">$${parseFloat(transaction.total_amount).toFixed(2)}</span>
            </div>
            ${transaction.created_by_name ? `
                <div>
                    <strong style="color: #7f8c8d; font-size: 12px;">Created By:</strong><br>
                    <span style="color: #2c3e50;">${transaction.created_by_name}</span>
                </div>
            ` : ''}
            ${transaction.posted_at ? `
                <div>
                    <strong style="color: #7f8c8d; font-size: 12px;">Posted At:</strong><br>
                    <span style="color: #2c3e50;">${new Date(transaction.posted_at).toLocaleString()}</span>
                </div>
            ` : ''}
        </div>

        ${isPendingApproval ? `
            <div style="background: #fff3cd; border-left: 5px solid #f39c12; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                <strong style="color: #856404; font-size: 14px;">⚠️ Pending Admin Approval</strong>
                <p style="margin: 8px 0 0 0; color: #856404; font-size: 13px; line-height: 1.5;">
                    This transaction requires admin approval due to special accounting rules (e.g., rare equity scenarios).
                    You'll be notified once it's reviewed.
                </p>
            </div>
        ` : ''}

        ${transaction.description ? `
            <div style="margin-bottom: 20px;">
                <strong style="color: #2c3e50; font-size: 14px;">Description:</strong><br>
                <p style="margin: 8px 0 0 0; color: #555; line-height: 1.5;">${transaction.description}</p>
            </div>
        ` : ''}

        ${transaction.reference_number ? `
            <div style="margin-bottom: 20px;">
                <strong style="color: #2c3e50; font-size: 14px;">Reference Number:</strong>
                <span style="color: #555;"> ${transaction.reference_number}</span>
            </div>
        ` : ''}

        <div>
            <strong style="color: #2c3e50; font-size: 14px; display: block; margin-bottom: 10px;">Transaction Lines:</strong>
            <div style="border: 2px solid #e9ecef; border-radius: 8px; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef;">Account</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef;">Type</th>
                            <th style="padding: 12px; text-align: right; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef;">Debit</th>
                            <th style="padding: 12px; text-align: right; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef;">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${transaction.lines.map((line, index) => `
                            <tr style="${index < transaction.lines.length - 1 ? 'border-bottom: 1px solid #e9ecef;' : ''}">
                                <td style="padding: 12px;">
                                    <div style="font-weight: 600; color: #2c3e50;">${line.account_name}</div>
                                    <div style="font-size: 12px; color: #7f8c8d; font-family: monospace;">${line.account_code}</div>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <span style="text-transform: uppercase; font-weight: 600; color: ${line.line_type === 'debit' ? '#27ae60' : '#e74c3c'}; font-size: 11px; background: ${line.line_type === 'debit' ? '#d4edda' : '#f8d7da'}; padding: 4px 8px; border-radius: 4px;">
                                        ${line.line_type}
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: right; color: #27ae60; font-weight: 700; font-size: 15px;">
                                    ${line.line_type === 'debit' ? '$' + parseFloat(line.amount).toFixed(2) : '-'}
                                </td>
                                <td style="padding: 12px; text-align: right; color: #e74c3c; font-weight: 700; font-size: 15px;">
                                    ${line.line_type === 'credit' ? '$' + parseFloat(line.amount).toFixed(2) : '-'}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; border-top: 2px solid #e9ecef;">
                            <td colspan="2" style="padding: 12px; text-align: right; font-weight: 700; color: #2c3e50;">TOTAL:</td>
                            <td style="padding: 12px; text-align: right; color: #27ae60; font-weight: 700; font-size: 15px;">
                                $${totalDebits.toFixed(2)}
                            </td>
                            <td style="padding: 12px; text-align: right; color: #e74c3c; font-weight: 700; font-size: 15px;">
                                $${totalCredits.toFixed(2)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        ${canEdit ? `
            <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e9ecef; display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeViewModal(); editTransaction(${transaction.id});" style="background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                    Edit Transaction
                </button>
                <button class="btn btn-secondary" onclick="closeViewModal(); postTransaction(${transaction.id});" style="background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                    Post Transaction
                </button>
                <button class="btn btn-secondary" onclick="closeViewModal(); deleteTransaction(${transaction.id}, '${transaction.transaction_number}');" style="background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                    Delete
                </button>
            </div>
        ` : ''}
    `;
}

// Close view modal
function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

// Edit transaction (load into modal)
async function editTransaction(id) {
    console.log('[TRANSACTIONS] Editing transaction #' + id);

    try {
        const response = await fetch(`/php/api/transactions/get.php?id=${id}`);
        const result = await response.json();

        if (result.success) {
            const transaction = result.data;

            // Can only edit pending transactions
            if (transaction.status_id != 1) {
                Notify.warning('Cannot edit this transaction', 'Only pending transactions can be edited.');
                return;
            }

            // Open transaction modal in edit mode
            openTransactionModalForEdit(transaction);
        } else {
            Notify.error('Error loading transaction', result.message);
        }
    } catch (error) {
        console.error('[TRANSACTIONS] Edit error:', error);
        Notify.error('Error loading transaction', error.message);
    }
}

// Open transaction modal for editing
function openTransactionModalForEdit(transaction) {
    console.log('[TRANSACTIONS] Opening modal for editing...');

    // Reset form
    document.getElementById('transactionForm').reset();
    document.getElementById('transactionId').value = transaction.id;
    document.getElementById('transactionModalTitle').textContent = '✏️ Edit Transaction';

    // Fill form
    document.getElementById('transactionDate').value = transaction.transaction_date;
    document.getElementById('description').value = transaction.description || '';
    document.getElementById('referenceNumber').value = transaction.reference_number || '';

    // Clear transaction lines
    transactionLines = [];
    lineCounter = 0;
    document.getElementById('transactionLinesContainer').innerHTML = '';

    // Add lines from transaction
    transaction.lines.forEach(line => {
        addTransactionLine();
        const lineId = lineCounter - 1;

        // Set values
        document.getElementById(`account-${lineId}`).value = line.account_id;
        document.getElementById(`type-${lineId}`).value = line.line_type;
        document.getElementById(`amount-${lineId}`).value = parseFloat(line.amount);
    });

    // Update balance display
    calculateBalance();

    // Show modal
    document.getElementById('transactionModal').style.display = 'flex';

    console.log('[TRANSACTIONS] ✅ Transaction modal opened for editing');
}

// Post transaction (convert pending to posted)
async function postTransaction(id) {
    Notify.confirm(
        'Are you sure you want to POST this transaction?\n\nThis will:\n• Update account balances\n• Lock the transaction (no more edits)\n• This action cannot be undone!',
        async () => {
            console.log('[TRANSACTIONS] Posting transaction #' + id);

            try {
                const response = await fetch('/php/api/transactions/post.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });

                const result = await response.json();

                if (result.success) {
                    Notify.success('Transaction posted successfully!');
                    loadTransactions(); // Reload list
                    loadAccounts(); // Reload accounts (balances updated)
                } else {
                    Notify.error('Error posting transaction', result.message || 'Unknown error');
                }
            } catch (error) {
                console.error('[TRANSACTIONS] Post error:', error);
                Notify.error('Error posting transaction. Please try again.', error.message);
            }
        },
        'Post Transaction',
        'Cancel'
    );
}

// Delete transaction (only pending)
async function deleteTransaction(id, transactionNumber) {
    Notify.confirm(
        `Are you sure you want to DELETE transaction ${transactionNumber}?\n\nThis action cannot be undone!`,
        async () => {
            console.log('[TRANSACTIONS] Deleting transaction #' + id);

            try {
                const response = await fetch('/php/api/transactions/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });

                const result = await response.json();

                if (result.success) {
                    Notify.success('Transaction deleted successfully!');
                    loadTransactions(); // Reload list
                } else {
                    Notify.error('Error deleting transaction', result.message || 'Unknown error');
                }
            } catch (error) {
                console.error('[TRANSACTIONS] Delete error:', error);
                Notify.error('Error deleting transaction. Please try again.', error.message);
            }
        },
        'Delete Transaction',
        'Cancel'
    );
}

// ==================== CLOSE PERIOD FUNCTIONALITY ====================

// Open close period modal
function openClosePeriodModal() {
    console.log('[TRANSACTIONS] Opening close period modal...');
    document.getElementById('closePeriodModal').style.display = 'flex';

    // Reset state
    document.getElementById('closePeriodSummary').innerHTML = `
        <div style="text-align: center; padding: 40px; color: #999;">
            <div style="font-size: 48px; margin-bottom: 15px;">📊</div>
            <p>Click "Calculate Period Summary" below to analyze current balances</p>
        </div>
    `;
    document.getElementById('calculateBtn').style.display = 'inline-block';
    document.getElementById('closePeriodBtn').style.display = 'none';
}

// Close close period modal
function closeClosePeriodModal() {
    document.getElementById('closePeriodModal').style.display = 'none';
}

// Calculate period summary
async function calculatePeriodSummary() {
    console.log('[TRANSACTIONS] Calculating period summary...');

    const calculateBtn = document.getElementById('calculateBtn');
    calculateBtn.textContent = 'Calculating...';
    calculateBtn.disabled = true;

    try {
        // Get all revenue accounts
        const revenueAccounts = allAccounts.filter(a => a.account_type_id === 4 && a.is_system_account != 1);
        const totalRevenue = revenueAccounts.reduce((sum, acc) => sum + parseFloat(acc.current_balance), 0);

        // Get all expense accounts
        const expenseAccounts = allAccounts.filter(a => a.account_type_id === 5 && a.is_system_account != 1);
        const totalExpenses = expenseAccounts.reduce((sum, acc) => sum + parseFloat(acc.current_balance), 0);

        // Calculate net income
        // Revenue is negative (credit balance), Expenses are positive (debit balance)
        // Net Income = -Revenue - Expenses  (both converted to positive for display)
        const netIncome = Math.abs(totalRevenue) - Math.abs(totalExpenses);
        const isProfit = netIncome > 0;

        // Find or check for Retained Earnings account
        const retainedEarnings = allAccounts.find(a =>
            a.account_type_id === 3 &&
            (a.account_name.toLowerCase().includes('retained') ||
             a.account_name.toLowerCase().includes('earnings'))
        );

        if (!retainedEarnings) {
            Notify.error('Retained Earnings account not found!', 'Please create a "Retained Earnings" equity account first.');
            calculateBtn.textContent = 'Calculate Period Summary';
            calculateBtn.disabled = false;
            return;
        }

        // Generate summary HTML
        let summaryHTML = `
            <div style="background: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 20px 0; color: #2c3e50; font-size: 18px; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px;">
                    📊 Period Summary
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                    <div style="background: #e8f5e9; padding: 15px; border-radius: 6px; border-left: 4px solid #27ae60;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Total Revenue</div>
                        <div style="font-size: 24px; font-weight: 700; color: #27ae60;">$${Math.abs(totalRevenue).toFixed(2)}</div>
                        <div style="font-size: 12px; color: #999; margin-top: 5px;">${revenueAccounts.length} account(s)</div>
                    </div>
                    
                    <div style="background: #ffebee; padding: 15px; border-radius: 6px; border-left: 4px solid #e74c3c;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Total Expenses</div>
                        <div style="font-size: 24px; font-weight: 700; color: #e74c3c;">$${Math.abs(totalExpenses).toFixed(2)}</div>
                        <div style="font-size: 12px; color: #999; margin-top: 5px;">${expenseAccounts.length} account(s)</div>
                    </div>
                </div>
                
                <div style="background: ${isProfit ? '#e3f2fd' : '#fff3e0'}; padding: 20px; border-radius: 8px; border: 2px solid ${isProfit ? '#2196f3' : '#f39c12'}; margin-bottom: 25px;">
                    <div style="text-align: center;">
                        <div style="font-size: 14px; color: #666; margin-bottom: 8px;">Net ${isProfit ? 'Profit' : 'Loss'}</div>
                        <div style="font-size: 36px; font-weight: 700; color: ${isProfit ? '#2196f3' : '#f39c12'};">
                            ${isProfit ? '+' : '-'}$${Math.abs(netIncome).toFixed(2)}
                        </div>
                        <div style="font-size: 13px; color: #666; margin-top: 8px;">
                            Will be transferred to: <strong>${retainedEarnings.account_name}</strong>
                        </div>
                    </div>
                </div>
                
                <div style="background: #f5f5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <h5 style="margin: 0 0 12px 0; color: #2c3e50; font-size: 15px;">🔄 Closing Entry Preview:</h5>
                    <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #ecf0f1;">
                                <th style="padding: 10px; text-align: left;">Account</th>
                                <th style="padding: 10px; text-align: right;">Debit</th>
                                <th style="padding: 10px; text-align: right;">Credit</th>
                                <th style="padding: 10px; text-align: right;">New Balance</th>
                            </tr>
                        </thead>
                        <tbody>`;

        // Add revenue accounts (will be debited to zero them out)
        revenueAccounts.forEach(acc => {
            const amount = Math.abs(acc.current_balance);
            if (amount > 0) {
                summaryHTML += `
                    <tr>
                        <td style="padding: 8px; border-top: 1px solid #ecf0f1;">${acc.account_name}</td>
                        <td style="padding: 8px; text-align: right; border-top: 1px solid #ecf0f1; color: #27ae60; font-weight: 600;">$${amount.toFixed(2)}</td>
                        <td style="padding: 8px; text-align: right; border-top: 1px solid #ecf0f1;">-</td>
                        <td style="padding: 8px; text-align: right; border-top: 1px solid #ecf0f1; font-weight: 600;">$0.00</td>
                    </tr>`;
            }
        });

        // Add expense accounts (will be credited to zero them out)
        expenseAccounts.forEach(acc => {
            const amount = Math.abs(acc.current_balance);
            if (amount > 0) {
                summaryHTML += `
                    <tr>
                        <td style="padding: 8px; border-top: 1px solid #ecf0f1;">${acc.account_name}</td>
                        <td style="padding: 8px; text-align: right; border-top: 1px solid #ecf0f1;">-</td>
                        <td style="padding: 8px; text-align: right; border-top: 1px solid #ecf0f1; color: #e74c3c; font-weight: 600;">$${amount.toFixed(2)}</td>
                        <td style="padding: 8px; text-align: right; border-top: 1px solid #ecf0f1; font-weight: 600;">$0.00</td>
                    </tr>`;
            }
        });

        // Add retained earnings entry
        const currentRetainedEarnings = parseFloat(retainedEarnings.current_balance);
        const newRetainedEarnings = currentRetainedEarnings + (isProfit ? -netIncome : netIncome);
        summaryHTML += `
            <tr style="background: #f9f9f9; font-weight: 600;">
                <td style="padding: 12px; border-top: 2px solid #bdc3c7;">${retainedEarnings.account_name}</td>
                <td style="padding: 12px; text-align: right; border-top: 2px solid #bdc3c7;">${!isProfit ? `$${Math.abs(netIncome).toFixed(2)}` : '-'}</td>
                <td style="padding: 12px; text-align: right; border-top: 2px solid #bdc3c7;">${isProfit ? `$${Math.abs(netIncome).toFixed(2)}` : '-'}</td>
                <td style="padding: 12px; text-align: right; border-top: 2px solid #bdc3c7; color: ${newRetainedEarnings >= 0 ? '#27ae60' : '#e74c3c'};">$${newRetainedEarnings.toFixed(2)}</td>
            </tr>
        </tbody>
    </table>
</div>

                <div style="background: #e3f2fd; padding: 15px; border-radius: 6px; border-left: 4px solid #2196f3;">
                    <strong style="color: #1565c0;">✅ What This Does:</strong>
                    <ul style="margin: 10px 0 0 20px; font-size: 13px; color: #1976d2; line-height: 1.8;">
                        <li>Zeroes out all Revenue and Expense accounts (they reset for next period)</li>
                        <li>Transfers Net ${isProfit ? 'Profit' : 'Loss'} to Retained Earnings</li>
                        <li>Your Assets and Liabilities <strong>DO NOT CHANGE</strong> (they already have the real money!)</li>
                        <li>Requires admin approval before posting</li>
                    </ul>
                </div>
            </div>
        `;

        document.getElementById('closePeriodSummary').innerHTML = summaryHTML;
        document.getElementById('calculateBtn').style.display = 'none';
        document.getElementById('closePeriodBtn').style.display = 'inline-block';

        console.log('[TRANSACTIONS] ✅ Period summary calculated');

    } catch (error) {
        console.error('[TRANSACTIONS] Error calculating period summary:', error);
        Notify.error('Error calculating summary', error.message);
        calculateBtn.textContent = 'Calculate Period Summary';
        calculateBtn.disabled = false;
    }
}

// Execute close period
async function executeClosePeriod() {
    console.log('[TRANSACTIONS] Executing close period...');

    const closePeriodBtn = document.getElementById('closePeriodBtn');
    closePeriodBtn.textContent = 'Processing...';
    closePeriodBtn.disabled = true;

    try {
        // Get all revenue accounts
        const revenueAccounts = allAccounts.filter(a => a.account_type_id === 4 && a.is_system_account != 1);
        const totalRevenue = revenueAccounts.reduce((sum, acc) => sum + parseFloat(acc.current_balance), 0);

        // Get all expense accounts
        const expenseAccounts = allAccounts.filter(a => a.account_type_id === 5 && a.is_system_account != 1);
        const totalExpenses = expenseAccounts.reduce((sum, acc) => sum + parseFloat(acc.current_balance), 0);

        // Calculate net income
        const netIncome = Math.abs(totalRevenue) - Math.abs(totalExpenses);
        const isProfit = netIncome > 0;

        // Find Retained Earnings account
        const retainedEarnings = allAccounts.find(a =>
            a.account_type_id === 3 &&
            (a.account_name.toLowerCase().includes('retained') ||
             a.account_name.toLowerCase().includes('earnings'))
        );

        if (!retainedEarnings) {
            throw new Error('Retained Earnings account not found');
        }

        // Build transaction lines
        const lines = [];

        // Add revenue accounts (debit to zero them out)
        revenueAccounts.forEach(acc => {
            const amount = Math.abs(acc.current_balance);
            if (amount > 0.01) {  // Only include if has significant balance
                lines.push({
                    account_id: acc.id,
                    line_type: 'debit',  // Debit revenue to close it
                    amount: amount
                });
            }
        });

        // Add expense accounts (credit to zero them out)
        expenseAccounts.forEach(acc => {
            const amount = Math.abs(acc.current_balance);
            if (amount > 0.01) {  // Only include if has significant balance
                lines.push({
                    account_id: acc.id,
                    line_type: 'credit',  // Credit expense to close it
                    amount: amount
                });
            }
        });

        // Add retained earnings entry (opposite of net income)
        if (Math.abs(netIncome) > 0.01) {
            lines.push({
                account_id: retainedEarnings.id,
                line_type: isProfit ? 'credit' : 'debit',  // Credit if profit, debit if loss
                amount: Math.abs(netIncome)
            });
        }

        if (lines.length === 0) {
            Notify.warning('No closing entries needed', 'All Revenue and Expense accounts are already at zero.');
            closePeriodBtn.textContent = 'Close Period & Submit for Approval';
            closePeriodBtn.disabled = false;
            return;
        }

        // Create the closing transaction
        const transactionData = {
            transaction_date: new Date().toISOString().split('T')[0],
            description: `Period Closing Entry - ${new Date().toLocaleDateString('en-US', {year: 'numeric', month: 'long'})} - Net ${isProfit ? 'Profit' : 'Loss'}: $${Math.abs(netIncome).toFixed(2)}`,
            reference_number: `CLOSE-${new Date().toISOString().split('T')[0]}`,
            status: 'posted',  // Will actually be pending with requires_approval = 1
            requires_approval: true,  // CRITICAL: This is a closing entry, needs admin approval
            lines: lines
        };

        console.log('[TRANSACTIONS] Closing entry data:', transactionData);

        // Save the transaction
        const response = await fetch('/php/api/transactions/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(transactionData)
        });

        const result = await response.json();

        if (result.success) {
            Notify.success('Period Closing Entry Submitted!', 'Transaction sent to admin for approval. Revenue & Expenses will be reset once approved.');
            closeClosePeriodModal();
            loadTransactions();  // Reload transactions list
            loadAccounts();  // Reload accounts
        } else {
            throw new Error(result.message || 'Failed to create closing entry');
        }

    } catch (error) {
        console.error('[TRANSACTIONS] Error executing close period:', error);
        Notify.error('Error creating closing entry', error.message);
        closePeriodBtn.textContent = 'Close Period & Submit for Approval';
        closePeriodBtn.disabled = false;
    }
}

console.log('[TRANSACTIONS] ✅ Script loaded successfully');

