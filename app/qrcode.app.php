<?php

class QrcodeApp extends MallbaseApp {

    function index() {
        import('phpqrcode');
        $value = $_GET['url'];
        $errorCorrectionLevel = "L";
        $matrixPointSize = "4";
        QRcode::png($value, false, $errorCorrectionLevel, $matrixPointSize);
        exit;
    }
}

?>
