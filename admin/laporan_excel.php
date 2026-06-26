<?php
/**
 * laporan_excel.php
 * Export laporan penjualan ke Excel (.xlsx) dengan format profesional
 * Ayam Penyet Bendungan Batusangkar
 *
 * Requires: composer require phpoffice/phpspreadsheet
 * Or: install via vendor/ in project root
 */
require_once '../koneksi.php';
requireRole('admin');

// ── Auto-load PhpSpreadsheet ───────────────────────────────────────────────
$autoload_paths = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];
$autoload_found = false;
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoload_found = true;
        break;
    }
}

// Fallback: gunakan CSV jika PhpSpreadsheet tidak tersedia
if (!$autoload_found || !class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    exportCSVFallback();
    exit;
}

// ── Parameter ──────────────────────────────────────────────────────────────
$periode = $_GET['periode'] ?? 'harian';
$tanggal = sanitize($_GET['tanggal'] ?? date('Y-m-d'));
$bulan   = (int)($_GET['bulan'] ?? date('m'));
$tahun   = (int)($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = (int)date('m');
if ($tahun < 2020 || $tahun > 2099) $tahun = (int)date('Y');

if ($periode === 'bulanan') {
    $where_tgl   = "MONTH(p.tanggal)=$bulan AND YEAR(p.tanggal)=$tahun";
    $where_nop   = "MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun";
    $judul_periode = date('F Y', mktime(0,0,0,$bulan,1,$tahun));
    $label_periode = 'Bulanan';
} elseif ($periode === 'tahunan') {
    $where_tgl   = "YEAR(p.tanggal)=$tahun";
    $where_nop   = "YEAR(tanggal)=$tahun";
    $judul_periode = "Tahun $tahun";
    $label_periode = 'Tahunan';
} else {
    $periode     = 'harian';
    $where_tgl   = "DATE(p.tanggal)='$tanggal'";
    $where_nop   = "DATE(tanggal)='$tanggal'";
    $judul_periode = date('d F Y', strtotime($tanggal));
    $label_periode = 'Harian';
}

// ── Ambil data ─────────────────────────────────────────────────────────────
$pesanan_list = [];
$res = $conn->query("SELECT p.*,
    (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan=p.id) as jml_item
    FROM pesanan p WHERE $where_tgl ORDER BY p.tanggal ASC");
while ($row = $res->fetch_assoc()) $pesanan_list[] = $row;

$total_omzet   = array_sum(array_column(array_filter($pesanan_list, fn($p) => ($p['status_bayar']??'')==='lunas'), 'total_harga'));
$total_pesanan = count($pesanan_list);
$total_selesai = count(array_filter($pesanan_list, fn($p) => $p['status']==='selesai'));
$total_lunas   = count(array_filter($pesanan_list, fn($p) => ($p['status_bayar']??'')==='lunas'));

$metode_stats = [];
$rm = $conn->query("SELECT COALESCE(metode_bayar,'cash') as metode_bayar, COUNT(*) as jml, SUM(total_harga) as total
    FROM pesanan WHERE $where_nop AND status_bayar='lunas' GROUP BY metode_bayar");
if ($rm) while ($row = $rm->fetch_assoc()) $metode_stats[$row['metode_bayar']] = $row;

$top_menu = [];
$res2 = $conn->query("SELECT dp.nama_menu, SUM(dp.jumlah) as total_terjual, SUM(dp.subtotal) as total_pendapatan
    FROM detail_pesanan dp JOIN pesanan p ON dp.id_pesanan=p.id
    WHERE $where_tgl AND p.status != 'dibatalkan'
    GROUP BY dp.id_menu, dp.nama_menu ORDER BY total_terjual DESC LIMIT 10");
while ($row = $res2->fetch_assoc()) $top_menu[] = $row;

$bulan_nm = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$ringkasan = [];
if ($periode === 'bulanan') {
    $rb = $conn->query("SELECT DATE(tanggal) as tgl, COUNT(*) as jml_pesanan,
        SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END) as omzet,
        SUM(CASE WHEN status_bayar='lunas' THEN 1 ELSE 0 END) as lunas
        FROM pesanan WHERE MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun
        GROUP BY DATE(tanggal) ORDER BY tgl ASC");
    if ($rb) while ($r = $rb->fetch_assoc()) $ringkasan[] = $r;
} elseif ($periode === 'tahunan') {
    $rt = $conn->query("SELECT MONTH(tanggal) as bln, COUNT(*) as jml_pesanan,
        SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END) as omzet,
        SUM(CASE WHEN status_bayar='lunas' THEN 1 ELSE 0 END) as lunas
        FROM pesanan WHERE YEAR(tanggal)=$tahun GROUP BY MONTH(tanggal) ORDER BY bln ASC");
    if ($rt) while ($r = $rt->fetch_assoc()) $ringkasan[] = $r;
}

// ── Build spreadsheet ──────────────────────────────────────────────────────
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Ayam Penyet Bendungan Batusangkar')
    ->setTitle("Laporan $label_periode - $judul_periode")
    ->setSubject('Laporan Penjualan')
    ->setDescription('Digenerate otomatis oleh sistem Klik Penyet');

// ════════════════════════════════════════════════════════
// SHEET 1: RINGKASAN
// ════════════════════════════════════════════════════════
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Ringkasan');

// Helper: style cell
$styleHeader = [
    'font'      => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>11,'name'=>'Calibri'],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1A1A2E']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
];
$styleSubHeader = [
    'font'      => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>10,'name'=>'Calibri'],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E84040']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
];
$styleRed = [
    'font'      => ['bold'=>true,'color'=>['rgb'=>'E84040'],'size'=>10],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF5F5']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT],
];
$styleTotals = [
    'font'      => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>10,'name'=>'Calibri'],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1A1A2E']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT,'vertical'=>Alignment::VERTICAL_CENTER],
];
$styleLabel = [
    'font'      => ['bold'=>true,'color'=>['rgb'=>'444444'],'size'=>9],
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F3F4F6']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
];
$styleValue = [
    'font'      => ['bold'=>false,'color'=>['rgb'=>'1A1A2E'],'size'=>9],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
];
$borderThin = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'E5E7EB']]]];
$borderMed  = ['borders'=>['outline' =>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'E84040']]]];

// ── A1: Judul ──────────────────────────────────────────
$sheet1->mergeCells('A1:F1');
$sheet1->setCellValue('A1', 'LAPORAN PENJUALAN ' . strtoupper($label_periode));
$sheet1->getStyle('A1')->applyFromArray($styleHeader);
$sheet1->getStyle('A1')->getFont()->setSize(14);
$sheet1->getRowDimension(1)->setRowHeight(34);

$sheet1->mergeCells('A2:F2');
$sheet1->setCellValue('A2', 'Ayam Penyet Bendungan Batusangkar — ' . $judul_periode);
$sheet1->getStyle('A2')->applyFromArray([
    'font' => ['italic'=>true,'color'=>['rgb'=>'888888'],'size'=>10],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);
$sheet1->getRowDimension(2)->setRowHeight(20);

$sheet1->mergeCells('A3:F3');
$sheet1->setCellValue('A3', 'Digenerate: ' . date('d F Y H:i') . ' WIB  |  Oleh: ' . ($_SESSION['nama'] ?? 'Admin'));
$sheet1->getStyle('A3')->applyFromArray([
    'font' => ['italic'=>true,'color'=>['rgb'=>'AAAAAA'],'size'=>9],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);
$sheet1->getRowDimension(3)->setRowHeight(16);
$sheet1->getRowDimension(4)->setRowHeight(10);

// ── Ringkasan Utama ────────────────────────────────────
$r = 5;
$sheet1->mergeCells("A{$r}:F{$r}");
$sheet1->setCellValue("A{$r}", '📊 RINGKASAN UTAMA');
$sheet1->getStyle("A{$r}")->applyFromArray($styleSubHeader);
$sheet1->getRowDimension($r)->setRowHeight(22);
$r++;

$summaryData = [
    ['Total Omzet (Lunas)',   formatRupiah($total_omzet),  true],
    ['Total Pesanan',         $total_pesanan,               false],
    ['Pesanan Selesai',       $total_selesai,               false],
    ['Transaksi Lunas',       $total_lunas,                 false],
    ['Periode',               $judul_periode,               false],
    ['Tipe Laporan',          $label_periode,               false],
];

foreach ($summaryData as [$lbl, $val, $isHighlight]) {
    $sheet1->setCellValue("A{$r}", $lbl);
    $sheet1->setCellValue("B{$r}", $val);
    $sheet1->mergeCells("B{$r}:F{$r}");
    $sheet1->getStyle("A{$r}")->applyFromArray($styleLabel);
    if ($isHighlight) {
        $sheet1->getStyle("B{$r}")->applyFromArray($styleRed);
    } else {
        $sheet1->getStyle("B{$r}")->applyFromArray($styleValue);
    }
    $sheet1->getStyle("A{$r}:F{$r}")->applyFromArray($borderThin);
    $sheet1->getRowDimension($r)->setRowHeight(18);
    $r++;
}
$r++;

// ── Metode Pembayaran ──────────────────────────────────
$sheet1->mergeCells("A{$r}:F{$r}");
$sheet1->setCellValue("A{$r}", '💳 BREAKDOWN METODE PEMBAYARAN');
$sheet1->getStyle("A{$r}")->applyFromArray($styleSubHeader);
$sheet1->getRowDimension($r)->setRowHeight(22);
$r++;

$mconf2 = ['cash'=>'Tunai / Cash','qris'=>'QRIS','transfer'=>'Transfer Bank'];
$sheet1->setCellValue("A{$r}", 'Metode');
$sheet1->setCellValue("B{$r}", 'Jumlah Transaksi');
$sheet1->setCellValue("C{$r}", 'Total Pendapatan');
$sheet1->mergeCells("C{$r}:F{$r}");
$sheet1->getStyle("A{$r}:F{$r}")->applyFromArray([
    'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'374151']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);
$sheet1->getRowDimension($r)->setRowHeight(18);
$r++;

foreach ($mconf2 as $k => $label) {
    $md = $metode_stats[$k] ?? null;
    $sheet1->setCellValue("A{$r}", $label);
    $sheet1->setCellValue("B{$r}", $md ? (int)$md['jml'] : 0);
    $sheet1->setCellValue("C{$r}", $md ? (int)$md['total'] : 0);
    $sheet1->mergeCells("C{$r}:F{$r}");
    $sheet1->getStyle("C{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
    $sheet1->getStyle("A{$r}:F{$r}")->applyFromArray($borderThin);
    $sheet1->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet1->getRowDimension($r)->setRowHeight(17);
    $r++;
}
$r++;

// ── Top Menu ───────────────────────────────────────────
if (!empty($top_menu)) {
    $sheet1->mergeCells("A{$r}:F{$r}");
    $sheet1->setCellValue("A{$r}", '🏆 MENU TERLARIS');
    $sheet1->getStyle("A{$r}")->applyFromArray($styleSubHeader);
    $sheet1->getRowDimension($r)->setRowHeight(22);
    $r++;

    $sheet1->setCellValue("A{$r}", 'Rank');
    $sheet1->setCellValue("B{$r}", 'Nama Menu');
    $sheet1->setCellValue("C{$r}", 'Total Terjual');
    $sheet1->setCellValue("D{$r}", 'Total Pendapatan');
    $sheet1->mergeCells("D{$r}:F{$r}");
    $sheet1->getStyle("A{$r}:F{$r}")->applyFromArray([
        'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'374151']],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet1->getRowDimension($r)->setRowHeight(18);
    $r++;

    foreach ($top_menu as $i => $m) {
        $sheet1->setCellValue("A{$r}", $i + 1);
        $sheet1->setCellValue("B{$r}", $m['nama_menu']);
        $sheet1->setCellValue("C{$r}", (int)$m['total_terjual']);
        $sheet1->setCellValue("D{$r}", (int)$m['total_pendapatan']);
        $sheet1->mergeCells("D{$r}:F{$r}");
        $sheet1->getStyle("D{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet1->getStyle("A{$r}:F{$r}")->applyFromArray($borderThin);
        $sheet1->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        if ($i % 2 === 0) {
            $sheet1->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
        }
        $sheet1->getRowDimension($r)->setRowHeight(17);
        $r++;
    }
}

// ── Ringkasan per hari/bulan (untuk bulanan & tahunan) ─
if (!empty($ringkasan)) {
    $r++;
    $sheet1->mergeCells("A{$r}:F{$r}");
    $ringkas_title = $periode === 'bulanan' ? '📅 RINGKASAN PER HARI' : '📆 RINGKASAN PER BULAN';
    $sheet1->setCellValue("A{$r}", $ringkas_title);
    $sheet1->getStyle("A{$r}")->applyFromArray($styleSubHeader);
    $sheet1->getRowDimension($r)->setRowHeight(22);
    $r++;

    $sheet1->setCellValue("A{$r}", $periode === 'bulanan' ? 'Tanggal' : 'Bulan');
    $sheet1->setCellValue("B{$r}", 'Jml Pesanan');
    $sheet1->setCellValue("C{$r}", 'Lunas');
    $sheet1->setCellValue("D{$r}", 'Omzet');
    $sheet1->mergeCells("D{$r}:F{$r}");
    $sheet1->getStyle("A{$r}:F{$r}")->applyFromArray([
        'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'374151']],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet1->getRowDimension($r)->setRowHeight(18);
    $rStart = $r + 1;
    $r++;

    foreach ($ringkasan as $i => $rk) {
        $label_r = $periode === 'bulanan'
            ? date('d F Y', strtotime($rk['tgl']))
            : $bulan_nm[(int)$rk['bln']];
        $sheet1->setCellValue("A{$r}", $label_r);
        $sheet1->setCellValue("B{$r}", (int)$rk['jml_pesanan']);
        $sheet1->setCellValue("C{$r}", (int)$rk['lunas']);
        $sheet1->setCellValue("D{$r}", (int)$rk['omzet']);
        $sheet1->mergeCells("D{$r}:F{$r}");
        $sheet1->getStyle("D{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet1->getStyle("A{$r}:F{$r}")->applyFromArray($borderThin);
        $sheet1->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        if ($i % 2 === 0) {
            $sheet1->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
        }
        $sheet1->getRowDimension($r)->setRowHeight(17);
        $r++;
    }
    // Total row
    $rEnd = $r - 1;
    $sheet1->setCellValue("A{$r}", 'TOTAL');
    $sheet1->setCellValue("B{$r}", "=SUM(B{$rStart}:B{$rEnd})");
    $sheet1->setCellValue("C{$r}", "=SUM(C{$rStart}:C{$rEnd})");
    $sheet1->setCellValue("D{$r}", "=SUM(D{$rStart}:D{$rEnd})");
    $sheet1->mergeCells("D{$r}:F{$r}");
    $sheet1->getStyle("D{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
    $sheet1->getStyle("A{$r}:F{$r}")->applyFromArray($styleTotals);
    $sheet1->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet1->getRowDimension($r)->setRowHeight(20);
}

// ── Column widths sheet 1 ──────────────────────────────
$sheet1->getColumnDimension('A')->setWidth(28);
$sheet1->getColumnDimension('B')->setWidth(20);
$sheet1->getColumnDimension('C')->setWidth(18);
$sheet1->getColumnDimension('D')->setWidth(20);
$sheet1->getColumnDimension('E')->setWidth(14);
$sheet1->getColumnDimension('F')->setWidth(14);
$sheet1->getStyle('A1:F1')->applyFromArray($borderMed);
$sheet1->getSheetView()->setShowGridLines(false);

// ════════════════════════════════════════════════════════
// SHEET 2: DETAIL PESANAN
// ════════════════════════════════════════════════════════
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Detail Pesanan');

// Title
$sheet2->mergeCells('A1:K1');
$sheet2->setCellValue('A1', 'DETAIL PESANAN — ' . strtoupper($judul_periode));
$sheet2->getStyle('A1')->applyFromArray($styleHeader);
$sheet2->getStyle('A1')->getFont()->setSize(13);
$sheet2->getRowDimension(1)->setRowHeight(30);

$sheet2->mergeCells('A2:K2');
$sheet2->setCellValue('A2', 'Ayam Penyet Bendungan Batusangkar | Digenerate: ' . date('d/m/Y H:i'));
$sheet2->getStyle('A2')->applyFromArray([
    'font' => ['italic'=>true,'color'=>['rgb'=>'888888'],'size'=>9],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);
$sheet2->getRowDimension(2)->setRowHeight(16);
$sheet2->getRowDimension(3)->setRowHeight(8);

// Header row
$headers = ['No','Kode Pesanan','Nama Pemesan','No. Meja','Tanggal','Waktu','Jml Item','Total (Rp)','Status','Status Bayar','Metode'];
$r2 = 4;
foreach ($headers as $ci => $hdr) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
    $sheet2->setCellValue("{$col}{$r2}", $hdr);
}
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
$sheet2->getStyle("A{$r2}:{$lastCol}{$r2}")->applyFromArray([
    'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9.5,'name'=>'Calibri'],
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E84040']],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders' => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'C0392B']]],
]);
$sheet2->getRowDimension($r2)->setRowHeight(22);
$r2++;

// Data rows
$ml2 = ['cash'=>'Tunai','qris'=>'QRIS','transfer'=>'Transfer'];
$totalSum = 0;

foreach ($pesanan_list as $i => $p) {
    $sb   = $p['status_bayar'] ?? 'belum_bayar';
    $mb   = $p['metode_bayar'] ?? '';
    $even = ($i % 2 === 0);

    $rowData = [
        $i + 1,
        $p['kode_pesanan'],
        (($p['nama_pelanggan']??'') && $p['nama_pelanggan']!=='Pelanggan') ? $p['nama_pelanggan'] : '',
        'Meja ' . $p['nomor_meja'],
        date('d/m/Y', strtotime($p['tanggal'])),
        date('H:i', strtotime($p['tanggal'])),
        (int)$p['jml_item'],
        (int)$p['total_harga'],
        ucfirst($p['status'] ?? ''),
        $sb === 'lunas' ? 'Lunas' : 'Belum Bayar',
        $mb ? ($ml2[$mb] ?? $mb) : '',
    ];

    foreach ($rowData as $ci => $val) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
        $sheet2->setCellValue("{$col}{$r2}", $val);
    }

    // Number format for Total
    $sheet2->getStyle("H{$r2}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
    $sheet2->getStyle("H{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet2->getStyle("A{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("G{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("D{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("E{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("F{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("J{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("K{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Row color
    if ($even) {
        $sheet2->getStyle("A{$r2}:{$lastCol}{$r2}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
    }
    // Lunas highlight
    if ($sb === 'lunas') {
        $sheet2->getStyle("J{$r2}")->applyFromArray([
            'font' => ['color'=>['rgb'=>'16A34A'],'bold'=>true],
        ]);
    } else {
        $sheet2->getStyle("J{$r2}")->applyFromArray([
            'font' => ['color'=>['rgb'=>'DC2626']],
        ]);
    }
    $sheet2->getStyle("A{$r2}:{$lastCol}{$r2}")->applyFromArray($borderThin);
    $sheet2->getRowDimension($r2)->setRowHeight(17);
    $totalSum += (int)$p['total_harga'];
    $r2++;
}

// Total footer
$sheet2->mergeCells("A{$r2}:G{$r2}");
$sheet2->setCellValue("A{$r2}", 'TOTAL');
$sheet2->setCellValue("H{$r2}", $totalSum);
$sheet2->getStyle("H{$r2}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
$sheet2->getStyle("A{$r2}:K{$r2}")->applyFromArray($styleTotals);
$sheet2->getStyle("H{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet2->getRowDimension($r2)->setRowHeight(22);

// Column widths sheet 2
$colWidths = [6, 20, 22, 12, 13, 9, 10, 18, 13, 14, 13];
foreach ($colWidths as $ci => $w) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
    $sheet2->getColumnDimension($col)->setWidth($w);
}
$sheet2->getSheetView()->setShowGridLines(false);
$sheet2->freezePane('A5');

// ── Set sheet aktif ke 1 ───────────────────────────────
$spreadsheet->setActiveSheetIndex(0);

// ── Output ─────────────────────────────────────────────
$filename = 'Laporan-' . $label_periode . '-' . str_replace([' ', '/'], '-', $judul_periode) . '.xlsx';

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

// ════════════════════════════════════════════════════════
// FALLBACK: CSV (jika PhpSpreadsheet tidak ada)
// ════════════════════════════════════════════════════════
function exportCSVFallback() {
    global $pesanan_list, $judul_periode, $label_periode, $total_omzet, $total_pesanan, $total_lunas, $total_selesai, $metode_stats, $top_menu, $bulan_nm;

    $filename = 'Laporan-' . $label_periode . '-' . str_replace([' ', '/'], '-', $judul_periode) . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    // Info header
    fputcsv($out, ['LAPORAN PENJUALAN ' . strtoupper($label_periode)]);
    fputcsv($out, ['Ayam Penyet Bendungan Batusangkar']);
    fputcsv($out, ['Periode', $judul_periode]);
    fputcsv($out, ['Digenerate', date('d F Y H:i') . ' WIB']);
    fputcsv($out, []);

    // Ringkasan
    fputcsv($out, ['=== RINGKASAN ===']);
    fputcsv($out, ['Total Omzet (Lunas)', 'Rp ' . number_format($total_omzet,0,',','.')]);
    fputcsv($out, ['Total Pesanan', $total_pesanan]);
    fputcsv($out, ['Pesanan Selesai', $total_selesai]);
    fputcsv($out, ['Transaksi Lunas', $total_lunas]);
    fputcsv($out, []);

    // Metode
    fputcsv($out, ['=== METODE PEMBAYARAN ===']);
    fputcsv($out, ['Metode', 'Jumlah', 'Total']);
    $ml2 = ['cash'=>'Tunai','qris'=>'QRIS','transfer'=>'Transfer'];
    foreach (['cash','qris','transfer'] as $k) {
        $md = $metode_stats[$k] ?? null;
        fputcsv($out, [$ml2[$k], $md ? $md['jml'] : 0, $md ? 'Rp '.number_format($md['total'],0,',','.') : 'Rp 0']);
    }
    fputcsv($out, []);

    // Detail
    fputcsv($out, ['=== DETAIL PESANAN ===']);
    fputcsv($out, ['No','Kode Pesanan','Nama Pemesan','Meja','Tanggal','Waktu','Jml Item','Total (Rp)','Status','Status Bayar','Metode']);
    foreach ($pesanan_list as $i => $p) {
        fputcsv($out, [
            $i+1,
            $p['kode_pesanan'],
            (($p['nama_pelanggan']??'') && $p['nama_pelanggan']!=='Pelanggan') ? $p['nama_pelanggan'] : '',
            'Meja '.$p['nomor_meja'],
            date('d/m/Y', strtotime($p['tanggal'])),
            date('H:i', strtotime($p['tanggal'])),
            $p['jml_item'],
            (int)$p['total_harga'],
            ucfirst($p['status']??''),
            ($p['status_bayar']??'')===  'lunas' ? 'Lunas' : 'Belum Bayar',
            $p['metode_bayar'] ? ($ml2[$p['metode_bayar']] ?? $p['metode_bayar']) : '',
        ]);
    }
    fclose($out);
}
