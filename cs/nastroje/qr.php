<?php 
  
include 'phpqrcode/qrlib.php'; 

$output_textate = date("Ymd");
//$iban = $_GET['iban'];
$amount = $_GET['amount'];
$vs = $_GET['vs'];

if($_GET['country'] == "cz") 
{
	$text = "SPD*1.0*ACC:CZ0420100000002200041594*AM:".$amount."*CC:CZK*X-VS:".$vs."*MSG:QRPLATBA"; 
	   
	$ecc = 'L'; 
	$fileixel_Size = 5; 
	$frame_Size = 5; 

	QRcode::png($text, false, $ecc, $fileixel_Size, $frame_size); 
}
else if($_GET['country'] == "sk") 
{
	//echo "At si madari naserou!"; 

	$outputate = date("Ymd");
	$iban = 'SK2083300000002601502873';//$_GET['iban'];
	$amount = $_GET['amount'];
	$vs = $_GET['vs'];

	$data = implode("\t", array(
		0 => '',
		1 => '1',
		2 => implode("\t", array(
			true,
			$amount,						// SUMA
			'EUR',						// JEDNOTKA
			$outputate,					// DATUM
			$vs,					// VARIABILNY SYMBOL
			'0',						// KONSTANTNY SYMBOL
			'0',						// SPECIFICKY SYMBOL
			'',
			'QR platba SK',					// POZNAMKA
			'1',
			$iban,	// IBAN
			'FIOZSKBA',					// SWIFT
			'0',
			'0'
		))
	));

	$output = strrev(hash("crc32b", $data, TRUE)) . $data;
	$x = proc_open("/usr/bin/xz '--format=raw' '--lzma1=lc=3,lp=0,pb=2,dict=128KiB' '-c' '-'", [0 => ["pipe", "r"], 1 => ["pipe", "w"]], $file);
	fwrite($file[0], $output);
	fclose($file[0]);
	$str = stream_get_contents($file[1]);
	fclose($file[1]);
	proc_close($x);
	$output = bin2hex("\x00\x00" . pack("v", strlen($output)) . $str);
	$text = "";
	for ($i = 0;$i < strlen($output);$i++) 
	{
		$text .= str_pad(base_convert($output[$i], 16, 2), 4, "0", STR_PAD_LEFT);
	}
	$len = strlen($text);
	$r = $len % 5;
	if ($r > 0) 
	{
		$file = 5 - $r;
		$text .= str_repeat("0", $file);
		$len += $file;
	}
	$len = $len / 5;
	$output = str_repeat("_", $len);
	for ($i = 0;$i < $len;$i += 1) 
	{
		$output[$i] = "0123456789ABCDEFGHIJKLMNOPQRSTUV"[bindec(substr($text, $i * 5, 5))];
	}
	//die($output);
	$ecc = 'L'; 
	$fileixel_Size = 4; 
	$frame_Size = 4; 

	QRcode::png($output, false, $ecc, $fileixel_Size, $frame_size);
	/*if (!empty($output)) {
		$u = '/chart?chs=125x124&cht=qr&chld=L|0&choe=UTF-8&chl=' . $output;
		$s = @fsockopen("chart.googleapis.com", 80, $e, $r, 1);
		if ($s) {
			$h = "GET " . $u . " HTTP/1.0\r\n";
			$h .= "Host: chart.googleapis.com\r\n";
			$h .= "Connection: close\r\n\r\n";
			fwrite($s, $h); $e = ''; $c = "";
			do {
				$e .= fgets($s, 128);
			} while (strpos($e, "\r\n\r\n") === false);
			while (!feof($s)) {
				$c .= fgets($s, 4096);
			}
			fclose($s);
		}
		header('Content-Type: image/png');
		echo $c;
	        echo $output; 
	}*/
}
 
?> 