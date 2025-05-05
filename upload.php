<?php
session_start();
$invoiceData = [];

error_reporting(0);
ini_set('display_errors', 0);
$target_dir = "uploads/";

$taxAmounts = [];
$amounts = [];

foreach ($_FILES["fileToUpload"]["tmp_name"] as $index => $tmpName) {
    if ($_FILES["fileToUpload"]["error"][$index] === UPLOAD_ERR_OK) {
        $fileName = basename($_FILES["fileToUpload"]["name"][$index]);
        $target_file = $target_dir . $fileName;

        if (move_uploaded_file($tmpName, $target_file)) {
            echo "File '$fileName' uploaded successfully.<br>";

            // Load XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($target_file);

            if ($xml === false) {
                echo "Failed loading XML '$fileName':<br>";
                foreach (libxml_get_errors() as $error) {
                    echo htmlentities($error->message) . "<br>";
                }
                continue;
            }

            // Namespaces
            $namespaces = $xml->getNamespaces(true);
            $cbc = $xml->children($namespaces['cbc']);
            $cac = $xml->children($namespaces['cac']);

            echo "Invoice ID: " . (string)$cbc->ID . "<br>";

            // Amount (without tax)
            $invoiceLine = $cac->InvoiceLine;
            $amount = $invoiceLine->children($namespaces['cbc'])->LineExtensionAmount;
            echo "Amount without tax: €" . (string)$amount . "<br>";

            $amountValue = (float)$amount;
            $amounts[] = $amountValue;

            // Tax amount
            $taxTotal = $cac->TaxTotal;
            $taxAmount = $taxTotal->children($namespaces['cbc'])->TaxAmount;
            echo "Tax Total: €" . (string)$taxAmount . "<br><br>";

            $taxAmountValue = (float)$taxAmount;
            $taxAmounts[] = $taxAmountValue;

            // Extract invoice date
            $invoiceDate = (string)$cbc->IssueDate;

            // Client name and VAT
            $clientParty = $cac->AccountingCustomerParty->children($namespaces['cac'])->Party;
            $clientName = (string)$clientParty->PartyName->children($namespaces['cbc'])->Name;
            $clientVAT = (string)$clientParty->PartyTaxScheme->children($namespaces['cbc'])->CompanyID;

            // Store for CSV and PDF
            $invoiceData[] = [
                'InvoiceNumber' => (string)$cbc->ID,
                'InvoiceDate' => $invoiceDate,
                'ExclBTW' => $amountValue,
                'BTW' => $taxAmountValue,
                'ClientName' => $clientName,
                'ClientVAT' => $clientVAT
            ];
        } else {
            echo "Error uploading '$fileName'.<br>";
        }
    } else {
        echo "Error with file index $index.<br>";
    }
}

// CSV download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_csv']) && isset($_SESSION['invoiceData'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=invoices.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['InvoiceNumber', 'InvoiceDate', 'ExclBTW', 'BTW', 'ClientName', 'ClientVAT']);

    foreach ($_SESSION['invoiceData'] as $row) {
        fputcsv($output, [
            $row['InvoiceNumber'],
            $row['InvoiceDate'],
            number_format($row['ExclBTW'], 2, '.', ''),
            number_format($row['BTW'], 2, '.', ''),
            $row['ClientName'],
            $row['ClientVAT']
        ]);
    }

    fclose($output);
    exit;
}

// PDF download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_pdf']) && isset($_SESSION['invoiceData'])) {
    require_once 'vendor/autoload.php';  // Ensure Composer's autoloader is used

    $pdf = new FPDF('L'); // 'L' for landscape orientation
    $pdf->AddPage();

    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(270, 10, 'Invoice Report', 0, 1, 'C');

    // Table Header
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'InvoiceNumber', 1, 0, 'C');
    $pdf->Cell(40, 10, 'InvoiceDate', 1, 0, 'C');
    $pdf->Cell(50, 10, 'ExclBTW', 1, 0, 'C');
    $pdf->Cell(50, 10, 'BTW', 1, 0, 'C');
    $pdf->Cell(60, 10, 'ClientName', 1, 0, 'C');
    $pdf->Cell(60, 10, 'ClientVAT', 1, 1, 'C');

    // Data Rows
    $pdf->SetFont('Arial', '', 12);
    foreach ($_SESSION['invoiceData'] as $row) {
        $pdf->Cell(40, 10, $row['InvoiceNumber'], 1, 0, 'C');
        $pdf->Cell(40, 10, $row['InvoiceDate'], 1, 0, 'C');
        $pdf->Cell(50, 10, number_format($row['ExclBTW'], 2, ',', '.'), 1, 0, 'C');
        $pdf->Cell(50, 10, number_format($row['BTW'], 2, ',', '.'), 1, 0, 'C');
        $pdf->Cell(60, 10, $row['ClientName'], 1, 0, 'C');
        $pdf->Cell(60, 10, $row['ClientVAT'], 1, 1, 'C');
    }

    // Output the PDF
    $pdf->Output('D', 'invoices.pdf');
    exit;
}

// Take sum of all invoice amounts
$totalAmount = array_sum($amounts);
$totalTaxAmount = array_sum($taxAmounts);
$totalIncludingTax = $totalAmount + $totalTaxAmount;

// Show total (without tax)
echo "<br><b>Total Amount without tax for All Invoices:</b><br>";
echo "Total Amount: €" . number_format($totalAmount, 2, ',', '.') . "<br>";

// Show tax total
echo "<br><b>Total Tax Amount for All Invoices:</b><br>";
echo "Total Tax: €" . number_format($totalTaxAmount, 2, ',', '.') . "<br>";

// Show total (with tax)
echo "<br><b>Total Amount with tax for All Invoices:</b><br>";
echo "Total Amount: €" . number_format($totalIncludingTax, 2, ',', '.') . "<br><br>";

// Show download CSV and PDF buttons
$_SESSION['invoiceData'] = $invoiceData;

echo '<form method="post">';
echo '<button type="submit" name="download_csv">Download CSV</button><br><br>';
echo '<button type="submit" name="download_pdf">Download PDF</button>';
echo '</form>';

?>