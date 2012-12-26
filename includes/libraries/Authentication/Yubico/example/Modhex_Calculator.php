<?php
/*
 * Created on May 25, 2009
 *
 */
require_once 'Modhex.php';
$FMT_TEXT = 'Plain text';
$FMT_DEC = 'Number';
$FMT_MODHEX = 'Modhex';
$FMT_B64 = 'Base64';
$FMT_HEX = 'Hex';
$FMT_OTP = 'OTP';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta http-equiv="Cache-Control" content="no-cache, must-revalidate">
    <title>Modehex Calculator</title>
    <link rel="stylesheet" type="text/css" href="style.css" />
    <style type="text/css">
        input[type="radio"] {
            width: 45px;
        }
    </style>
</head>
<body onLoad="document.getElementById('srctext').focus();">
    <div id="stripe">
        &nbsp;
    </div>

    <div id="container">
        <div id="logoArea">
                   <img src="yubicoLogo.gif" alt="yubicoLogo" width="150" height="75"/>
        </div>
        <div id="greenBarContent">
            <div id="greenBarImage">
                <img src="yubikey.jpg" alt="yubikey" width="150" height="89"/>
            </div>
            <div id="greenBarText">
                <h3>
                    Modhex Calculator
                </h3>
            </div>
        </div>
        <div id="bottomContent">
<?php
$srctext = trim($_REQUEST["srctext"]);
$srcfmt = $_REQUEST["srcfmt"];
$srcfmt_org = $srcfmt? $srcfmt: "P";
$srcfmt_desc = '';

if (strlen($srctext) > 0) {

    if ($srcfmt == "O") {
        $srcfmt_desc = $FMT_OTP;
        $srctext = substr($srctext, 0, 12);
        $srcfmt = "M";
    }

    $b64txt = $srctext;
    if ($srcfmt == "P") {
        $srcfmt_desc = $FMT_TEXT;
        $b64txt = base64_encode($srctext);
    } elseif ($srcfmt == "H") {
        $srcfmt_desc = $FMT_HEX;
        $hexval = $srctext;
        $b64txt = hexToB64($hexval);
        //echo 'Test B64 : '.$b64txt.' :: '.$hexval;
    } elseif ($srcfmt == "M") {
        if ($srcfmt_desc == '') {
            $srcfmt_desc = $FMT_MODHEX;
        }
        if ((strlen($srctext) % 2) == 1) {
            $srctext = 'c' . $srctext;
        }
        $b64txt = modhexToB64($srctext);
    } elseif ($srcfmt == "N") {
        $srcfmt_desc = $FMT_DEC;
        //$numval = intval($srctext);
        $numval = gmp_init($srctext, 10);
        $hexval = gmp_strval($numval,16);
        //echo 'Test Val : '.$numval.' :: '.$hexval;
        $b64txt = hexToB64($hexval);
        //echo 'Test B64 : '.$b64txt;
    } else {
        $srcfmt_desc = $FMT_B64;
    }
    //$devId_b64 = modhexToB64($devId);
?>
            <fieldset>
                <legend><b>Result</b></legend>
                <b>Input string: </b><?php echo $srctext;?> (<?php echo $srcfmt_desc;?>)<br/><br>
                <b>Output string</b> (in various formats):
                <ul>
                    <li><?php echo $FMT_TEXT . ': ' . base64_decode($b64txt); ?></li>
                    <li><?php echo $FMT_DEC . ': ' . gmp_strval(gmp_init(b64ToHex($b64txt),16)); ?></li>
                    <li><?php echo $FMT_MODHEX . ' encoded: ' . b64ToModhex($b64txt); ?></li>
                    <li><?php echo $FMT_B64 . ' encoded: ' . $b64txt; ?></li>
                    <li><?php echo $FMT_HEX . ' encoded: ' . b64ToHex($b64txt); ?></li>
                </ul>
            </fieldset>
            <br>
<?php
}
?>
            <form action=Modhex_Calculator.php method=post autocomplete=off>
                <fieldset>
                    <legend><b>Number scheme calculator</b></legend>
                    <ol>
                        <li>Choose format.</li>
                        <li>Enter string.</li>
                        <li>Press &quot;Convert to all formats&quot; button.</li>
                    </ol>
                    <b>Source format:</b><br/>
                    <input type="radio" name="srcfmt" value="P" <?php echo ($srcfmt_org == "P")?'checked':''; ?>>Plain text<br/>
                        <input type="radio" name="srcfmt" value="N" <?php echo ($srcfmt_org == "N")?'checked':''; ?>>Number<br/>
                        <input type="radio" name="srcfmt" value="B" <?php echo ($srcfmt_org == "B")?'checked':''; ?>>Base64<br/>
                        <input type="radio" name="srcfmt" value="H" <?php echo ($srcfmt_org == "H")?'checked':''; ?>>Hex<br/>
                        <input type="radio" name="srcfmt" value="O" <?php echo ($srcfmt_org == "O")?'checked':''; ?>>OTP<br/>
                        <input type="radio" name="srcfmt" value="M" <?php echo ($srcfmt_org == "M")?'checked':''; ?>>Modhex<br/><br/>

                    <b>String:</b>&nbsp;
                    <input name="srctext" id="srctext" value="<?php echo $srctext?>" size=50 maxlength=50><br/><br/>

                    <input type="submit" value="Convert to all formats">
                </fieldset>
            </form>
        </div>
    </div>
</body>
</html>
