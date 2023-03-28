<?php

require __DIR__ . '/../vendor/autoload.php';

header('Content-type: application/pdf');
header('Content-Disposition: attachment; filename="user_report_'.date('Y-m-d').'.pdf"');

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->Write(0, 'Hello World');
$pdf->Output();