<?php
/**
 * Agreement Generator - PDF generation helper for rental agreements
 * Path: modules/customers/functions/agreement-generator.php
 */

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate a rental agreement PDF using Dompdf or a simple HTML output.
 *
 * @param array $agreement  Full rental_agreements row with joins
 * @param array $customer   Customer row
 * @param array $vehicle    Vehicle row
 * @return string           Path to the generated PDF file (relative to BASE_PATH)
 * @throws Exception
 */
function generateAgreementPDF(array $agreement, array $customer, array $vehicle): string
{

    $agreementNo = $agreement['agreement_number'];
    $filename = $agreementNo . '.pdf';
    $savePath = AGREEMENTS_PATH . $filename;

    if (!is_dir(dirname($savePath))) {
        mkdir(dirname($savePath), 0755, true);
    }

    // Dompdf implementation
    if (class_exists(Dompdf::class)) {
        $options = new Options();
        $options->set('isDefaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        
        $html = "
            <h2 style='text-align: center;'>GENSAN CAR RENTAL SERVICES</h2>
            <h3 style='text-align: center;'>RENTAL AGREEMENT - {$agreementNo}</h3>
            <br>
            <p><strong>Customer:</strong> {$customer['first_name']} {$customer['last_name']}</p>
            <p><strong>Vehicle:</strong> {$vehicle['brand']} {$vehicle['model']} - {$vehicle['plate_number']}</p>
            <p><strong>Rental Period:</strong> {$agreement['rental_start_date']} to {$agreement['rental_end_date']}</p>
            <p><strong>Total Amount:</strong> PHP " . number_format($agreement['total_amount'], 2) . "</p>
        ";
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($savePath, $dompdf->output());
    } else {
        // Fallback: write HTML content as plain text stub
        $content = "GENSAN CAR RENTAL SERVICES\n";
        $content .= "RENTAL AGREEMENT: {$agreementNo}\n\n";
        $content .= "Customer: {$customer['first_name']} {$customer['last_name']}\n";
        $content .= "Vehicle : {$vehicle['brand']} {$vehicle['model']} ({$vehicle['plate_number']})\n";
        $content .= "Period  : {$agreement['rental_start_date']} to {$agreement['rental_end_date']}\n";
        $content .= "Total   : PHP " . number_format($agreement['total_amount'], 2) . "\n";
        file_put_contents($savePath, $content);
    }

    return 'assets/images/uploads/agreements/' . $filename;
}
