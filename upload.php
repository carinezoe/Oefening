<?php

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
        } else {
            echo "Error uploading '$fileName'.<br>";
        }
    } else {
        echo "Error with file index $index.<br>";
    }
}

$totalAmount = array_sum($amounts);
$totalTaxAmount = array_sum($taxAmounts);
$totalIncludingTax = $totalAmount + $totalTaxAmount;

echo "<br><b>Total Amount without tax for All Invoices:</b><br>";
echo "Total Amount: €" . number_format($totalAmount, 2, ',', '.') . "<br>";

echo "<br><b>Total Tax Amount for All Invoices:</b><br>";
echo "Total Tax: €" . number_format($totalTaxAmount, 2, ',', '.') . "<br>";

echo "<br><b>Total Amount with tax for All Invoices:</b><br>";
echo "Total Amount: €" . number_format($totalIncludingTax, 2, ',', '.') . "<br>";

?>