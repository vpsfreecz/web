<?php

require_once __DIR__ . '/../../vendor/autoload.php';
use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeNone;
use Endroid\QrCode\Writer\PngWriter;
use rikudou\SkQrPayment\QrPayment;
use Rikudou\Iban\Iban\IBAN;

$amount = $_GET['amount'];
$vs = $_GET['vs'];

if ($_GET['country'] == 'cz') {
    $text = 'SPD*1.0*ACC:CZ0420100000002200041594*AM:' . $amount
        . '*CC:CZK*X-VS:' . $vs . '*MSG:QRPLATBA';
} else if ($_GET['country'] == 'sk') {
    $payment = new QrPayment(new IBAN('SK2083300000002601502873'));
    $payment->setAmount(floatval($amount))
        ->setComment('QR platba SK')
        ->setCurrency('EUR')
        ->setVariableSymbol($vs)
        ->setPayeeName('vpsFree.cz')
        ->setXzBinary('/run/current-system/sw/bin/xz');

    $text = $payment->getQrString();
} else {
    exit;
}

// Use the same symbol size and opaque white border for both payment formats.
// Render at high resolution so existing emails can keep their image dimensions.
$qrCode = QrCode::create($text)
    ->setSize(580)
    ->setMargin(80)
    ->setRoundBlockSizeMode(new RoundBlockSizeModeNone())
    ->setBackgroundColor(new Color(255, 255, 255));

// The quiet zone must always span at least four modules, even for smaller codes.
$matrix = (new MatrixFactory())->create($qrCode);
$qrCode->setMargin(max(80, (int) ceil(4 * $matrix->getBlockSize())));

$result = (new PngWriter())->write($qrCode);
header('Content-Type: image/png');
echo $result->getString();
