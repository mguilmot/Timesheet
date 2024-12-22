<?php
// First, handle any AJAX requests before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['CONTENT_TYPE']) && 
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    
    // Configuration
    $BASE_DIR = dirname(__FILE__);
    $CONFIG = [
        'data_dir' => $BASE_DIR . '/data/',
        'settings_file' => $BASE_DIR . '/data/settings.json',
    ];

    // Ensure data directory exists
    if (!file_exists($CONFIG['data_dir'])) {
        mkdir($CONFIG['data_dir'], 0775, true);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $response = ['success' => false];

    error_log("Processing AJAX request: " . $input['action']);

    switch ($input['action']) {
        case 'save_timesheet':
            try {
                $year = preg_replace('/[^0-9]/', '', $input['year']);
                $month = preg_replace('/[^0-9]/', '', $input['month']);
                $filename = sprintf('%s/timesheet_%s_%s.json', $CONFIG['data_dir'], $year, $month);
                
                error_log("Saving timesheet to: " . $filename);
                
                if (file_put_contents($filename, json_encode($input['data'], JSON_PRETTY_PRINT))) {
                    $response['success'] = true;
                    error_log("Successfully saved timesheet");
                } else {
                    error_log("Failed to save timesheet");
                    $response['error'] = 'Failed to write file';
                }
            } catch (Exception $e) {
                error_log("Error saving timesheet: " . $e->getMessage());
                $response['error'] = $e->getMessage();
            }
            break;

        case 'load_timesheet':
            try {
                $year = preg_replace('/[^0-9]/', '', $input['year']);
                $month = preg_replace('/[^0-9]/', '', $input['month']);
                $filename = sprintf('%s/timesheet_%s_%s.json', $CONFIG['data_dir'], $year, $month);
                
                error_log("Loading timesheet from: " . $filename);
                
                if (file_exists($filename)) {
                    $content = file_get_contents($filename);
                    $data = json_decode($content, true);
                    $response['success'] = true;
                    $response['data'] = $data;
                    error_log("Successfully loaded timesheet");
                } else {
                    error_log("No existing timesheet found");
                    $response['success'] = true;
                    $response['data'] = null;
                }
            } catch (Exception $e) {
                error_log("Error loading timesheet: " . $e->getMessage());
                $response['error'] = $e->getMessage();
            }
            break;

        case 'save_settings':
            try {
                error_log("Saving settings to: " . $CONFIG['settings_file']);
                if (file_put_contents($CONFIG['settings_file'], json_encode($input['settings'], JSON_PRETTY_PRINT))) {
                    $response['success'] = true;
                    error_log("Successfully saved settings");
                } else {
                    error_log("Failed to save settings");
                    $response['error'] = 'Failed to write settings file';
                }
            } catch (Exception $e) {
                error_log("Error saving settings: " . $e->getMessage());
                $response['error'] = $e->getMessage();
            }
            break;

        case 'load_settings':
            try {
                error_log("Loading settings from: " . $CONFIG['settings_file']);
                if (file_exists($CONFIG['settings_file'])) {
                    $content = file_get_contents($CONFIG['settings_file']);
                    $settings = json_decode($content, true);
                    $response['success'] = true;
                    $response['settings'] = $settings;
                    error_log("Successfully loaded settings");
                } else {
                    error_log("No existing settings found");
                    $response['success'] = true;
                    $response['settings'] = ['client' => '', 'manager' => ''];
                }
            } catch (Exception $e) {
                error_log("Error loading settings: " . $e->getMessage());
                $response['error'] = $e->getMessage();
            }
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Application</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-2">
    <!-- Single main container -->
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-3">
        <!-- Header -->
        <div class="flex justify-between items-center mb-2">
            <h1 class="text-lg font-bold">Timesheet</h1>
            <div class="flex space-x-2">
                <select id="yearSelect" class="rounded border p-1 text-xs">
                    <?php
                    $currentYear = date('Y');
                    for($y = $currentYear - 5; $y <= $currentYear + 5; $y++) {
                        $selected = ($y == $currentYear) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
                <select id="monthSelect" class="rounded border p-1 text-xs">
                    <?php
                    $months = array('January', 'February', 'March', 'April', 'May', 'June', 
                                  'July', 'August', 'September', 'October', 'November', 'December');
                    $currentMonth = date('n') - 1;  // 0-based month
                    foreach($months as $index => $month) {
                        $selected = ($index == $currentMonth) ? 'selected' : '';
                        echo "<option value='$index' $selected>$month</option>";
                    }
                    ?>
                </select>
                <button onclick="generatePDF()" class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600">
                    Export to PDF
                </button>
            </div>
        </div>

        <!-- Client and Manager inputs -->
        <div class="mb-2 flex justify-between items-center text-xs">
            <div>
                <label class="font-bold mr-1">Client:</label>
                <input type="text" id="clientInput" class="border rounded p-0.5 w-40">
            </div>
            <div>
                <label class="font-bold mr-1">Manager:</label>
                <input type="text" id="managerInput" class="border rounded p-0.5 w-40">
            </div>
        </div>

        <!-- Timesheet table -->
        <table id="timesheetTable" class="w-full text-xs border-collapse">
            <thead>
                <tr class="bg-gray-100">
                    <th class="p-0.5 text-left w-24 border-b">Day</th>
                    <th class="p-0.5 text-left w-24 border-b">Date</th>
                    <th class="p-0.5 text-left w-16 border-b">Hours</th>
                    <th class="p-0.5 text-left border-b">Reason</th>
                </tr>
            </thead>
            <tbody>
                <!-- JavaScript will populate this -->
            </tbody>
        </table>

        <!-- Signature -->
        <div class="mt-2 pt-1 border-t text-xs">
            <p class="font-bold">Signature: _____________________________________</p>
            <p class="text-xs text-gray-500 mt-0.5">Date: <?php echo date('d/m/Y'); ?></p>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <script>
        // Global PDF generation function
        function generatePDF() {
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                const monthSelect = document.getElementById('monthSelect');
                const yearSelect = document.getElementById('yearSelect');
                const monthName = monthSelect.options[monthSelect.selectedIndex].text;
                const year = yearSelect.value;

                // Add title
                doc.setFontSize(11);
                doc.text(`Timesheet - Mike Guilmot - ${monthName} ${year}`, doc.internal.pageSize.getWidth() / 2, 15, { align: 'center' });

                // Add client info
                doc.setFontSize(10);
                const client = document.getElementById('clientInput').value || '________________';
                doc.text(`Client: ${client}`, 14, 25);

                // Calculate total days
                let totalHours = 0;
                const rows = document.querySelectorAll('#timesheetTable tbody tr');
                rows.forEach(row => {
                    const hoursValue = row.querySelector('td:nth-child(3) input').value;
                    totalHours += parseFloat(hoursValue) || 0;
                });
                const totalDays = (totalHours / 8).toFixed(2);

                // Prepare table data
                const tableData = [];
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    tableData.push([
                        cells[0].textContent,
                        cells[1].textContent,
                        cells[2].querySelector('input').value,
                        cells[3].querySelector('input').value || '-'
                    ]);
                });

                // Generate table
                doc.autoTable({
                    startY: 30,
                    head: [['Day', 'Date', 'Hours', 'Reason']],
                    body: tableData,
                    theme: 'grid',
                    styles: { 
                        fontSize: 8,
                        cellPadding: 1
                    },
                    headStyles: {
                        fillColor: [51, 102, 204],  // Dark blue
                        textColor: 255,  // White
                        fontStyle: 'bold'
                    },
                    columnStyles: {
                        0: { cellWidth: 25 },
                        1: { cellWidth: 25 },
                        2: { cellWidth: 15 },
                        3: { cellWidth: 'auto' }
                    },
                    didParseCell: function(data) {
                        // Add gray background to weekend rows, but only for body rows not header
                        if (data.section === 'body') {
                            const rowIndex = data.row.index;
                            const date = new Date(year, monthSelect.value, rowIndex + 1);
                            if (date.getDay() === 0 || date.getDay() === 6) {
                                data.cell.styles.fillColor = [200, 200, 200];  // Darker gray for weekends
                            }
                        }
                    }
                });

                // Add total days
                const finalY = doc.previousAutoTable.finalY + 10;
                doc.setFontSize(9);
                doc.text(`Total days: ${totalDays}`, 14, finalY);

                // Add manager and signature with slightly more space between them
                const manager = document.getElementById('managerInput').value || '________________';
                doc.text(`Manager: ${manager}`, 14, finalY + 15);
                doc.text('Signature: _____________________________________', 14, finalY + 30);  // Increased from +25 to +30
                doc.setFontSize(8);
                doc.text(`Date: ${formatDate(new Date())}`, 14, finalY + 35);  // Increased from +30 to +35

                // Save the PDF
                doc.save(`timesheet_${monthName.toLowerCase()}_${year}.pdf`);
            } catch (error) {
                console.error('PDF Generation Error:', error);
            }
        }
        function formatDate(date) {
            return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        async function saveTimesheet() {
            console.log('saveTimesheet called');
            const year = document.getElementById('yearSelect').value;
            const month = document.getElementById('monthSelect').value;
            const rows = document.querySelectorAll('#timesheetTable tbody tr');
            
            const timesheetData = Array.from(rows).map(row => ({
                day: row.cells[0].textContent,
                date: row.cells[1].textContent,
                hours: row.querySelector('input[type="text"]').value,
                reason: row.querySelectorAll('input[type="text"]')[1].value
            }));

            console.log('Saving timesheet data:', timesheetData);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'save_timesheet',
                        year: year,
                        month: month,
                        data: timesheetData
                    })
                });
                
                const result = await response.json();
                console.log('Save response:', result);
                
                if (!result.success) {
                    console.error('Error saving timesheet:', result.error);
                }
            } catch (error) {
                console.error('Error saving timesheet:', error);
            }
        }

        function createTimesheet() {
            console.log('Creating timesheet');
            const year = parseInt(document.getElementById('yearSelect').value);
            const month = parseInt(document.getElementById('monthSelect').value);
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const tbody = document.querySelector('#timesheetTable tbody');
            
            tbody.innerHTML = '';
            
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dayName = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getDay()];
                const isWeekend = date.getDay() === 0 || date.getDay() === 6;
                
                const tr = document.createElement('tr');
                tr.className = isWeekend ? 'bg-gray-300' : '';  // Darker gray for weekends
                
                tr.innerHTML = `
                    <td class="p-0.5 border-b">${dayName}</td>
                    <td class="p-0.5 border-b">${formatDate(date)}</td>
                    <td class="p-0.5 border-b">
                        <input type="text" value="0" class="border rounded p-0.5 w-12 text-right text-xs">
                    </td>
                    <td class="p-0.5 border-b">
                        <input type="text" value="" class="border rounded p-0.5 w-full text-xs">
                    </td>
                `;
                
                tbody.appendChild(tr);
            }

            // Add event listeners after creating the elements
            console.log('Adding input event listeners');
            const debouncedSave = debounce(() => saveTimesheet(), 500);
            tbody.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', debouncedSave);
            });
        }

        async function saveSettings() {
            console.log('Saving settings...');
            const settings = {
                client: document.getElementById('clientInput').value,
                manager: document.getElementById('managerInput').value
            };

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'save_settings',
                        settings: settings
                    })
                });
                
                const result = await response.json();
                console.log('Settings save response:', result);
                
                if (!result.success) {
                    console.error('Error saving settings:', result.error);
                }
            } catch (error) {
                console.error('Error in settings save:', error);
            }
        }

        async function loadSettings() {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'load_settings'
                    })
                });
                
                const result = await response.json();
                console.log('Settings load response:', result);
                
                if (result.success && result.settings) {
                    document.getElementById('clientInput').value = result.settings.client || '';
                    document.getElementById('managerInput').value = result.settings.manager || '';
                }
            } catch (error) {
                console.error('Error loading settings:', error);
            }
        }

        async function loadTimesheet() {
            const year = document.getElementById('yearSelect').value;
            const month = document.getElementById('monthSelect').value;

            console.log('Loading timesheet for:', year, month);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'load_timesheet',
                        year: year,
                        month: month
                    })
                });
                
                const result = await response.json();
                console.log('Load response:', result);
                
                if (result.success) {
                    if (result.data) {
                        // First create empty timesheet
                        createTimesheet();
                        
                        // Then fill in the saved data
                        const rows = document.querySelectorAll('#timesheetTable tbody tr');
                        result.data.forEach((dayData, index) => {
                            if (rows[index]) {
                                const inputs = rows[index].querySelectorAll('input');
                                inputs[0].value = dayData.hours;
                                inputs[1].value = dayData.reason || '';
                            }
                        });
                    } else {
                        // No saved data, just create empty timesheet
                        createTimesheet();
                    }
                } else {
                    console.error('Error loading timesheet:', result.error);
                    createTimesheet();
                }
            } catch (error) {
                console.error('Error loading timesheet:', error);
                createTimesheet();
            }
        }

        // Set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            loadTimesheet();  // Load initial timesheet
            loadSettings();
            
            // Month/Year change handlers
            document.getElementById('yearSelect').addEventListener('change', loadTimesheet);
            document.getElementById('monthSelect').addEventListener('change', loadTimesheet);
            
            // Settings change handlers
            const debouncedSaveSettings = debounce(saveSettings, 500);
            document.getElementById('clientInput').addEventListener('input', debouncedSaveSettings);
            document.getElementById('managerInput').addEventListener('input', debouncedSaveSettings);
        });
    </script>
</body>
</html>