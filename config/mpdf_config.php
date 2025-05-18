<?php
require_once '../lib/mpdf/vendor/autoload.php';

function initMPDF() {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 40,
        'margin_bottom' => 25,
        'margin_header' => 10,
        'margin_footer' => 10,
        'default_font' => 'helvetica',
        'tempDir' => __DIR__ . '/../../pdf/tmp',
        'defaultPageNumStyle' => '1',
        'autoScriptToLang' => true,
        'autoLangToFont' => true,
        'useSubstitutions' => true
    ]);

    // Configuration des en-têtes et pieds de page
    $mpdf->SetHTMLHeader('
    <div style="text-align: right; font-size: 9pt; color: #666; border-bottom: 1px solid #eee; padding-bottom: 5px;">
        Généré le ' . date('d/m/Y à H:i') . '
    </div>');

    $mpdf->SetHTMLFooter('
    <div style="text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #eee; padding-top: 5px;">
        Page {PAGENO} sur {nbpg} | Système de Gestion des Absences - ENSA Marrakech
    </div>');

    return $mpdf;
}

function generateAbsencesPDF($absences, $title) {
    $mpdf = initMPDF();
    $mpdf->SetTitle($title);
    $mpdf->SetAuthor('ENSA Marrakech');
    $mpdf->SetCreator('Système de Gestion des Absences');
    $mpdf->SetSubject('Rapport des absences');
    $mpdf->SetKeywords('ENSA, Absences, Rapport, Université Cadi Ayyad');
    return $mpdf;
}
