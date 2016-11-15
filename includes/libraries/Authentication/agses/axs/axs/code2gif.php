<?php
    /****m* code2gif.php
     * VERSION
     *  CODE2GIF v2.00 2011.02.23
     * DESCRIPTION
     *  The script returns an animated GIF image of a Flicker Code String. The
     *  animated GIF is created by combining pre-processed GIF image building
     *  blocks.
     *
     * INPUT
     *  GET code:string         flicker code string to be displayed by the animated
     *                          GIF. The code must consist of hexadecimal characters
     *                          and its length must not exceed 1024 characters (512
     *                          flicker code frames). If code is not specified the
     *                          script will default to a dummy "wiper pattern".
     *
     *  GET delay:number        number of milliseconds each frame of the animated
     *                          GIF is shown. Animated GIF can only deal with delays
     *                          that are multiples of 10ms. Most webbrowsers will
     *                          limit the minimum display time for a frame to about
     *                          80ms (Firefox 3+ being the exception, allowing
     *                          delays of 20ms and maybe less). If delay is not
     *                          specified, the script will default to 80ms.
     *
     * DEPENDENCIES
     *  The script requires two data files to reside in the location specified by
     *  the "$path" variable below:
     *  * head.bin              preprocessed image header file
     *  * data.bin              preprocessed image data file
     *
     * EXAMPLE
     *  Create a dummy Flicker Code with a speed of 12FPS:
     *  code2gif.php?code=20100804020102040810&delay=80
     *
     *  Create a dummy Flicker Code that flashes on and off at 10 FPS.
     *  code2gif.php?code=003f&delay=100
     *
     * HISTORY
     *  v1.00 20101027 initial release
     *  v2.00 20110223 complete redesign; removed the dependency on the external
     *                 application (gifsicle) that had to be installed on the
     *                 server to use the previous version. reduced size of the
     *                 created gif files by almost 50%.
     */

	// path to data files
	$path = './images_gif';
	
	// get code from GET request, use dummy pattern if code not defined
	$code = isset($_GET['code']) ? $_GET['code'] : '20100804020102040810';

	// get frame delay from GET request, default to "safe speed" if delay not
	// defined or invalid. delay is specified in ms in the request and needs
	// to be converted to 1/100s for use in GIF files
	$delay = isset($_GET['delay']) ? ($_GET['delay']+0)/10 : 8;
	$delay = ($delay > 0) ? $delay : 8;

	// code string must only contain hex characters, code length is limited to
	// 1024 characters (512 frames)
	$codelen = strlen($code);
	if ( ctype_xdigit($code) && $codelen < 1024 ) {
	
		// get prepared gif file header
		$gif  = file_get_contents($path.'/head.bin');
		
		// build graphic control extension block
		// disposal method   = 0
		// transparent color = 255
		$ext = "\x21\xf9\x04\x01".chr($delay&0xff).chr(($delay>>8)&0xff)."\xff\x00";
		
		// output image header
		header('Content-type:image/gif');
		print($gif);
	
		// append image data blocks as necessary
		$size = filesize($path.'/data.bin');
		$data = fopen($path.'/data.bin','r');
		if ($data) {

			// load the index from the data file and unpack it into a PHP array.
			// the index array contains the file offsets of the 4096 delta and
			// base images inside the data file.
			$index = unpack("V*", fread($data, 64*64*4));

			// when a code is repeated (prev and curr have the same value) a
			// base image is inserted instead of a delta image. the first frame
			// in the animation must be a base image, so we peek at the flicker
			// code and set $prev to the first value in the string
			$prev = hexdec(substr($code, 0, 2));
			for ($i = 0; $i < $codelen; $i+=2) {

				// get current code
				$curr = hexdec(substr($code, $i, 2));
						
				// locate image block in data file
				$blockIdx = $prev*64+$curr+1;	// array starts at 1
				$blockPos = $index[$blockIdx];
				$blockLen = ($blockIdx < 4096 ? $index[$blockIdx+1] : $size) - $blockPos;
				
				// output extension header
				print($ext);

				// output image block
				fseek($data, $blockPos);
				print(fread($data, $blockLen));
			
				$prev = $curr;
			}

			// file terminator
			print(";");
		}
	}
    /******/
?>