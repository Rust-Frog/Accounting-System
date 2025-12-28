<?php

namespace Domain\Reporting\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class ReportExportService
{
    public function exportToCsv(array $data, array $headers): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        
        return $content;
    }

    public function exportToPdf(string $html, string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        
        // Set paper size and orientation
        $dompdf->setPaper('A4', $orientation);
        
        // Render the HTML as PDF
        $dompdf->render();
        
        return $dompdf->output();
    }
}
