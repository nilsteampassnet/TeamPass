<?php


/*******************************************************************************
* TTFontFile class                                                             *
*                                                                              *
* This class is based on The ReportLab Open Source PDF library                 *
* written in Python - http://www.reportlab.com/software/opensource/            *
* together with ideas from the OpenOffice source code and others.              *
*                                                                              *
* Version:  1.02                                                               *
* Date:     2010-06-07                                                         *
* Author:   Ian Back <ianb@bpm1.com>                                           *
* License:  LGPL                                                               *
* Copyright (c) Ian Back, 2010                                                 *
* This header must be retained in any redistribution or                        *
* modification of the file.                                                    *
*                                                                              *
*******************************************************************************/


// TrueType Font Glyph operators
define("GF_WORDS",(1 << 0));
define("GF_SCALE",(1 << 3));
define("GF_MORE",(1 << 5));
define("GF_XYSCALE",(1 << 6));
define("GF_TWOBYTWO",(1 << 7));



class TTFontFile {

var $_pos;
var $numTables;
var $searchRange;
var $entrySelector;
var $rangeShift;
var $tables;
var $otables;
var $filename;
var $fh;
var $hmetrics;
var $glyphPos;
var $charToGlyph;
var $ascent;
var $descent;
var $name;
var $familyName;
var $styleName;
var $fullName;
var $uniqueFontID;
var $unitsPerEm;
var $bbox;
var $capHeight;
var $stemV;
var $italicAngle;
var $flags;
var $underlinePosition;
var $underlineThickness;
var $charWidths;
var $defaultWidth;
var $maxStrLenRead;

	function TTFontFile() {
		$this->maxStrLenRead = 200000;	// Maximum size of glyf table to read in as string (otherwise reads each glyph from file)
	}


	function getMetrics($file, $presubset=0) {
		$this->filename = $file;
		$this->fh = fopen($file,'rb') or die('Can\'t open file ' . $file);
		$this->_pos = 0;
		$this->charWidths = array();
		$this->hmetrics = array();
		$this->glyphPos = array();
		$this->charToGlyph = array();
		$this->tables = array();
		$this->otables = array();
		$this->ascent = 0;
		$this->descent = 0;
		if ($presubset) { $this->skip(4); }
		else { $this->readHeader(); }
		$this->readTableDirectory($presubset);
		$this->extractInfo($presubset); 
		fclose($this->fh);
	}

	function readHeader() {
		// read the sfnt header at the current position
		$this->version = $version = $this->read_ulong();
		if ($version==0x4F54544F) 
			die("Postscript outlines are not supported");
		if ($version==0x74746366) 
			die("TTC Font collections are not supported");
		if (!in_array($version, array(0x00010000,0x74727565)))
			die("Not a TrueType font: version=".$version);
		return true;
	}

	function readTableDirectory($presubset=1) {
		$this->numTables = $this->read_ushort();
            $this->searchRange = $this->read_ushort();
            $this->entrySelector = $this->read_ushort();
            $this->rangeShift = $this->read_ushort();
            $this->tables = array();	
            for ($i=0;$i<$this->numTables;$i++) {	// 1.02
                $record = array();
                $record['tag'] = $this->read_tag();
                $record['checksum'] = array($this->read_ushort(),$this->read_ushort());
                $record['offset'] = $this->read_ulong();
                $record['length'] = $this->read_ulong();
                $this->tables[$record['tag']] = $record;
		}
		if (!$presubset) $this->checksumTables();
	}

	function checksumTables() {
		// Check the checksums for all tables
		foreach($this->tables AS $t) {
		  if ($t['length'] > 0 && $t['length'] < $this->maxStrLenRead) {	// 1.02
            	$table = $this->get_chunk($t['offset'], $t['length']);
            	$checksum = $this->calcChecksum($table);
            	if ($t['tag'] == 'head') {
				$up = unpack('n*', substr($table,8,4));
				$adjustment[0] = $up[1];
				$adjustment[1] = $up[2];
            		$checksum = $this->sub32($checksum, $adjustment);
			}
            	$xchecksum = $t['checksum'];
            	if ($xchecksum != $checksum) 
            	    die(sprintf('TTF file "%s": invalid checksum %s table: %s (expected %s)', $this->filename,dechex($checksum[0]).dechex($checksum[1]),$t['tag'],dechex($xchecksum[0]).dechex($xchecksum[1])));
		  }
		}
	}

	function sub32($x, $y) {
		$xlo = $x[1];
		$xhi = $x[0];
		$ylo = $y[1];
		$yhi = $y[0];
		if ($ylo > $xlo) { $xlo += 1 << 16; $yhi += 1; }
		$reslo = $xlo-$ylo;
		if ($yhi > $xhi) { $xhi += 1 << 16;  }
		$reshi = $xhi-$yhi;
		$reshi = $reshi & 0xFFFF;
		return array($reshi, $reslo);
	}

	function calcChecksum($data)  {
		if (strlen($data) % 4) { $data .= str_repeat("\0",(4-(strlen($data) % 4))); }
		$hi=0x0000;
		$lo=0x0000;
		for($i=0;$i<strlen($data);$i+=4) {
			$hi += (ord($data[$i])<<8) + ord($data[$i+1]);
			$lo += (ord($data[$i+2])<<8) + ord($data[$i+3]);
			$hi += $lo >> 16;
			$lo = $lo & 0xFFFF;
			$hi = $hi & 0xFFFF;
		}
		return array($hi, $lo);
	}

	function get_table_pos($tag) {
		$offset = $this->tables[$tag]['offset'];
		$length = $this->tables[$tag]['length'];
		return array($offset, $length);
	}

	function seek($pos) {
		$this->_pos = $pos;
		fseek($this->fh,$this->_pos);
	}

	function skip($delta) {
		$this->_pos = $this->_pos + $delta;
		fseek($this->fh,$this->_pos);
	}

	function seek_table($tag, $offset_in_table = 0) {
		$tpos = $this->get_table_pos($tag);
		$this->_pos = $tpos[0] + $offset_in_table;
		fseek($this->fh, $this->_pos);
		return $this->_pos;
	}

	function read_tag() {
		$this->_pos += 4;
		return fread($this->fh,4);
	}

	function read_short() {
		$this->_pos += 2;
		$s = fread($this->fh,2);
		$a = (ord($s[0])<<8) + ord($s[1]);
		if ($a & (1 << 15) ) { $a = ($a - (1 << 16)) ; }
		return $a;
	}

	function read_ushort() {
		$this->_pos += 2;
		$s = fread($this->fh,2);
		return (ord($s[0])<<8) + ord($s[1]);
	}

	function read_ulong() {
		$this->_pos += 4;
		$s = fread($this->fh,4);
		// if large uInt32 as an integer, PHP converts it to -ve
		return (ord($s[0])*16777216) + (ord($s[1])<<16) + (ord($s[2])<<8) + ord($s[3]); // 	16777216  = 1<<24
	}

	function get_ushort($pos) {
		fseek($this->fh,$pos);
		$s = fread($this->fh,2);
		return (ord($s[0])<<8) + ord($s[1]);
	}

	function get_ulong($pos) {
		fseek($this->fh,$pos);
		$s = fread($this->fh,4);
		// iF large uInt32 as an integer, PHP converts it to -ve
		return (ord($s[0])*16777216) + (ord($s[1])<<16) + (ord($s[2])<<8) + ord($s[3]); // 	16777216  = 1<<24
	}

	function pack_short($val) {
		if ($val<0) { 
			$val = abs($val);
			$val = ~$val;
			$val += 1;
		}
		return pack("n",$val); 
	}

	function splice($stream, $offset, $value) {
		return substr($stream,0,$offset) . $value . substr($stream,$offset+strlen($value));
	}

	function _set_ushort($stream, $offset, $value) {
		$up = pack("n", $value);
		return $this->splice($stream, $offset, $up);
	}

	function get_chunk($pos, $length) {
		fseek($this->fh,$pos);
		if ($length <1) { return ''; }	// 1.02
		return (fread($this->fh,$length));
	}

	function get_table($tag) {
		list($pos, $length) = $this->get_table_pos($tag);
		if ($length == 0) { die('Truetype font ('.$this->filename.'): error reading table: '.$tag); }	// 1.02
		fseek($this->fh,$pos);
		return (fread($this->fh,$length));
	}

	function add($tag, $data) {
		if ($tag == 'head') {
			$data = $this->splice($data, 8, "\0\0\0\0");
		}
		$this->otables[$tag] = $data;
	}



/////////////////////////////////////////////////////////////////////////////////////////


	function extractInfo($presubset=0) {
		// = 0 ; validate; does not need glyphPos or charToGlyph
		// presubset = 1; no validation; does not need name and other metrics
		///////////////////////////////////
		// name - Naming table
		///////////////////////////////////
		if (!$presubset) { 
			$name_offset = $this->seek_table("name");
			$format = $this->read_ushort();
			if ($format != 0)
				die("Unknown name table format ".$format);
			$numRecords = $this->read_ushort();
			$string_data_offset = $name_offset + $this->read_ushort();

			$names = array(1=>'',2=>'',3=>'',4=>'',6=>'');
			$K = array_keys($names);
			$nameCount = count($names);
			for ($i=0;$i<$numRecords; $i++) {
				$platformId = $this->read_ushort();
				$encodingId = $this->read_ushort();
				$languageId = $this->read_ushort();
				$nameId = $this->read_ushort();
				$length = $this->read_ushort();
				$offset = $this->read_ushort();
				if (!in_array($nameId,$K)) continue;
				$N = '';
				if ($platformId == 3 && $encodingId == 1 && $languageId == 0x409) { // Microsoft, Unicode, US English, PS Name
					$opos = $this->_pos;
					$this->seek($string_data_offset + $offset);
					if ($length % 2 != 0)
						die("PostScript name is UTF-16BE string of odd length");
					$length /= 2;
					$N = '';
					while ($length > 0) {
						$char = $this->read_ushort();
						$N .= (chr($char));	// 1.02
						$length -= 1;
					}
					$this->_pos = $opos;
					$this->seek($opos);
				}
				else if ($platformId == 1 && $encodingId == 0 && $languageId == 0) { // Macintosh, Roman, English, PS Name
					$opos = $this->_pos;
					$N = $this->get_chunk($string_data_offset + $offset, $length);
					$this->_pos = $opos;
					$this->seek($opos);
				}
				if ($N && $names[$nameId]=='') {
					$names[$nameId] = $N;
					$nameCount -= 1;
					if ($nameCount==0) break;
				}
			}
			if ($names[6])
				$psName = preg_replace('/ /','-',$names[6]);
			else if ($names[4])
				$psName = preg_replace('/ /','-',$names[4]);
			else if ($names[1])
				$psName = preg_replace('/ /','-',$names[1]);
			else
				$psName = '';
			if (!$psName)
				die("Could not find PostScript font name");
			for ($i=0;$i<strlen($psName);$i++) {
				$c = $psName{$i};	// 1.02
				$oc = ord($c);
				if ($oc>126 || strpos(' [](){}<>/%',$c)!==false)
					die("psName=".$psName." contains invalid character ".$c." ie U+".ord(c));
			}
			$this->name = $psName;
			if ($names[1]) { $this->familyName = $names[1]; } else { $this->familyName = $psName; }
			if ($names[2]) { $this->styleName = $names[2]; } else { $this->styleName = 'Regular'; }
			if ($names[4]) { $this->fullName = $names[4]; } else { $this->fullName = $psName; }
			if ($names[3]) { $this->uniqueFontID = $names[3]; } else { $this->uniqueFontID = $psName; }
		}

		///////////////////////////////////
		// head - Font header table
		///////////////////////////////////
		$this->seek_table("head");
		if ($presubset) { $this->skip(18); }
		else {
			$ver_maj = $this->read_ushort();
			$ver_min = $this->read_ushort();
			if ($ver_maj != 1)
				die('Unknown head table version '. $ver_maj .'.'. $ver_min);
			$this->fontRevision = $this->read_ushort() . $this->read_ushort();

			$this->skip(4);
			$magic = $this->read_ulong();
			if ($magic != 0x5F0F3CF5) 
				die('Invalid head table magic ' .$magic);
			$this->skip(2);
		}
		$this->unitsPerEm = $unitsPerEm = $this->read_ushort();	// 1.02
		$scale = 1000 / $unitsPerEm;	// 1.02
		if ($presubset) { $this->skip(30); }	// 1.02
		else { 
			$this->skip(16);
			$xMin = $this->read_short();
			$yMin = $this->read_short();
			$xMax = $this->read_short();
			$yMax = $this->read_short();
			$this->bbox = array(($xMin*$scale), ($yMin*$scale), ($xMax*$scale), ($yMax*$scale));
			$this->skip(3*2);
		}
		$indexToLocFormat = $this->read_ushort();
		$glyphDataFormat = $this->read_ushort();
		if ($glyphDataFormat != 0)
			die('Unknown glyph data format '.$glyphDataFormat);

		///////////////////////////////////
		// hhea metrics table
		///////////////////////////////////
		// ttf2t1 seems to use this value rather than the one in OS/2 - so put in for compatibility
		if (isset($this->tables["hhea"])) {
			$this->seek_table("hhea");
			$this->skip(4);
			$hheaAscender = $this->read_short();
			$hheaDescender = $this->read_short();
			$this->ascent = ($hheaAscender *$scale);
			$this->descent = ($hheaDescender *$scale);
		}

		///////////////////////////////////
		// OS/2 - OS/2 and Windows metrics table
		///////////////////////////////////
		if (isset($this->tables["OS/2"])) {
			$this->seek_table("OS/2");
			$version = $this->read_ushort();
			$this->skip(2);
			$usWeightClass = $this->read_ushort();
			$this->skip(2);
			$fsType = $this->read_ushort();
	//		if ($fsType == 0x0002 || ($fsType & 0x0300) != 0) 
	//			die('Font does not allow subsetting/embedding');
			$this->skip(58);   //11*2 + 10 + 4*4 + 4 + 3*2
			$sTypoAscender = $this->read_short();
			$sTypoDescender = $this->read_short();
			if (!$this->ascent) $this->ascent = ($sTypoAscender*$scale);
			if (!$this->descent) $this->descent = ($sTypoDescender*$scale);
			if ($version > 1) {
				$this->skip(16);
				$sCapHeight = $this->read_short();
				$this->capHeight = ($sCapHeight*$scale);
			}
			else {
				$this->capHeight = $this->ascent;
			}
		}
		else {
			$usWeightClass = 500;
			if (!$this->ascent) $this->ascent = ($yMax*$scale);
			if (!$this->descent) $this->descent = ($yMin*$scale);
			$this->capHeight = $this->ascent;
		}
		$this->stemV = 50 + intval(pow(($usWeightClass / 65.0),2));

		///////////////////////////////////
		// post - PostScript table
		///////////////////////////////////
		$this->seek_table("post");
		if ($presubset) { $this->skip(4); }
		else {
			$ver_maj = $this->read_ushort();
			$ver_min = $this->read_ushort();
			if ($ver_maj <1 || $ver_maj >4) 
				die('Unknown post table version '.$ver_maj);
		}
		$this->italicAngle = $this->read_short() + $this->read_ushort() / 65536.0;
		$this->underlinePosition = $this->read_short() * $scale;
		$this->underlineThickness = $this->read_short() * $scale;
		$isFixedPitch = $this->read_ulong();

		$this->flags = 4;

		if ($this->italicAngle!= 0) 
			$this->flags = $this->flags | 64;
		if ($usWeightClass >= 600)
			$this->flags = $this->flags | 262144;
		if ($isFixedPitch)
			$this->flags = $this->flags | 1;

		///////////////////////////////////
		// hhea - Horizontal header table
		///////////////////////////////////
		$this->seek_table("hhea");
		if ($presubset) { $this->skip(32); }
		else {
			$ver_maj = $this->read_ushort();
			$ver_min = $this->read_ushort();
			if ($ver_maj != 1)
				die('Unknown hhea table version '.$ver_maj);
			$this->skip(28);
		}
		$metricDataFormat = $this->read_ushort();
		if ($metricDataFormat != 0)
			die('Unknown horizontal metric data format '.$metricDataFormat);
		$numberOfHMetrics = $this->read_ushort();
		if ($numberOfHMetrics == 0) 
			die('Number of horizontal metrics is 0');

		///////////////////////////////////
		// maxp - Maximum profile table
		///////////////////////////////////
		$this->seek_table("maxp");
		if ($presubset) { $this->skip(4); }
		else {
			$ver_maj = $this->read_ushort();
			$ver_min = $this->read_ushort();
			if ($ver_maj != 1)
				die('Unknown maxp table version '.$ver_maj);
		}
		$numGlyphs = $this->read_ushort();

		///////////////////////////////////
		// cmap - Character to glyph index mapping table
		///////////////////////////////////
		$cmap_offset = $this->seek_table("cmap");
		$this->skip(2);
		$cmapTableCount = $this->read_ushort();
		$unicode_cmap_offset = 0;
		for ($i=0;$i<$cmapTableCount;$i++) {
			$platformID = $this->read_ushort();
			$encodingID = $this->read_ushort();
			$offset = $this->read_ulong();
			$save_pos = $this->_pos;
			if ($platformID == 3 && $encodingID == 1) { // Microsoft, Unicode
				$format = $this->get_ushort($cmap_offset + $offset);
				if ($format == 4) {
					$unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			}
			else if ($platformID == 0) { // Unicode -- assume all encodings are compatible
				$format = $this->get_ushort($cmap_offset + $offset);
				if ($format == 4) {
					$unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			}
			$this->seek($save_pos );
		}

		if (!$unicode_cmap_offset)
			die('Font does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)');
		$this->seek($unicode_cmap_offset + 2);
		$length = $this->read_ushort();
		$limit = $unicode_cmap_offset + $length;
		$this->skip(2);

		$segCount = $this->read_ushort() / 2;
		$this->skip(6);
		$endCount = array();
		for($i=0; $i<$segCount; $i++) { $endCount[] = $this->read_ushort(); }
		$this->skip(2);
		$startCount = array();
		for($i=0; $i<$segCount; $i++) { $startCount[] = $this->read_ushort(); }
		$idDelta = array();
		for($i=0; $i<$segCount; $i++) { $idDelta[] = $this->read_short(); }		// ???? was unsigned short
		$idRangeOffset_start = $this->_pos;
		$idRangeOffset = array();
		for($i=0; $i<$segCount; $i++) { $idRangeOffset[] = $this->read_ushort(); }

		$glyphToChar = array();
		$charToGlyph = array();
		for ($n=0;$n<$segCount;$n++) {
			for ($unichar=$startCount[$n];$unichar<($endCount[$n] + 1);$unichar++) {
				if ($idRangeOffset[$n] == 0)
					$glyph = ($unichar + $idDelta[$n]) & 0xFFFF;
				else {
					$offset = ($unichar - $startCount[$n]) * 2 + $idRangeOffset[$n];
					$offset = $idRangeOffset_start + 2 * $n + $offset;
					if ($offset >= $limit)
						$glyph = 0;
					else {
						$glyph = $this->get_ushort($offset);
						if ($glyph != 0)
						$glyph = ($glyph + $idDelta[$n]) & 0xFFFF;
					}
				}
				if ($presubset) $charToGlyph[$unichar] = $glyph;
				$glyphToChar[$glyph][] = $unichar;
			}
		}
		if ($presubset) $this->charToGlyph = $charToGlyph;

		///////////////////////////////////
		// hmtx - Horizontal metrics table
		///////////////////////////////////
		$this->seek_table("hmtx");
		$aw = 0;
		$this->charWidths = array();
		$this->hmetrics = array();
		for( $glyph=0; $glyph<$numberOfHMetrics; $glyph++) {
			$aw = $this->read_ushort();
			$lsb = $this->read_short();
			$this->hmetrics[] = array($aw, $lsb);
			$aw = $scale*$aw;
			if ($glyph == 0)
				$this->defaultWidth = $aw;
			if (isset($glyphToChar[$glyph])) {
				foreach($glyphToChar[$glyph] AS $char) {
					$this->charWidths[$char] = round($aw);
				}
			}
		}
		for( $glyph=$numberOfHMetrics; $glyph<$numGlyphs; $glyph++) {
			$lsb = $this->read_ushort();
			$this->hmetrics[] = array($aw, $lsb);
			if (isset($glyphToChar[$glyph])) {
				foreach($glyphToChar[$glyph] AS $char) {
					$this->charWidths[$char] = round($aw);
				}
			}
		}

		///////////////////////////////////
		// loca - Index to location
		///////////////////////////////////
		if ($presubset) {
			$this->seek_table('loca');
			$this->glyphPos = array();
			if ($indexToLocFormat == 0) {
				for ($n=0; $n<=$numGlyphs; $n++) {	// 1.02
					$this->glyphPos[] = ($this->read_ushort() * 2);
				}
			}
			else if ($indexToLocFormat == 1) {
				for ($n=0; $n<=$numGlyphs; $n++) {	// 1.02
					$this->glyphPos[] = ($this->read_ulong());
				}
			}
			else 
				die('Unknown location table format '.$indexToLocFormat);
		}
	}


/////////////////////////////////////////////////////////////////////////////////////////


	function makeSubset($subset) {
		$this->fh = fopen($this->filename ,'rb') or die('Can\'t open file ' . $this->filename );
		$this->otables = array();
		$glyphMap = array(0=>0); 
		$glyphSet = array(0=>0);
		$codeToGlyph = array();
		foreach($subset AS $code) {
			if (isset($this->charToGlyph[$code]))
				$originalGlyphIdx = $this->charToGlyph[$code];
			else
				$originalGlyphIdx = 0;
			if (!isset($glyphSet[$originalGlyphIdx])) {
				$glyphSet[$originalGlyphIdx] = count($glyphMap);
				$glyphMap[] = $originalGlyphIdx;
			}
			$codeToGlyph[$code] = $glyphSet[$originalGlyphIdx];
		}

		list($start,$dummy) = $this->get_table_pos('glyf');

		$n = 0;
		while ($n < count($glyphMap)) {
			$originalGlyphIdx = $glyphMap[$n];
			$glyphPos = $this->glyphPos[$originalGlyphIdx];
			$glyphLen = $this->glyphPos[$originalGlyphIdx + 1] - $glyphPos;
			$n += 1;
			if (!$glyphLen) continue;
			$this->seek($start + $glyphPos);
			$numberOfContours = $this->read_short();
			if ($numberOfContours < 0) {
				$this->skip(8);
				$flags = GF_MORE;
				while ($flags & GF_MORE) {
					$flags = $this->read_ushort();
					$glyphIdx = $this->read_ushort();
					if (!isset($glyphSet[$glyphIdx])) {
						$glyphSet[$glyphIdx] = count($glyphMap);
						$glyphMap[] = $glyphIdx;
					}
					if ($flags & GF_WORDS)
						$this->skip(4);
					else
						$this->skip(2);
					if ($flags & GF_SCALE)
						$this->skip(2);
					else if ($flags & GF_XYSCALE)
						$this->skip(4);
					else if ($flags & GF_TWOBYTWO)
						$this->skip(8);
				}
			}
		}

		$numGlyphs = $n = count($glyphMap);
		while ($n > 1 && $this->hmetrics[$n][0] == $this->hmetrics[$n - 1][0]) { $n -= 1; }
		$numberOfHMetrics = $n;

		//tables copied from the original
		$tags = array ('name', 'OS/2', 'prep');
		foreach($tags AS $tag) { $this->add($tag, $this->get_table($tag)); }
		$tags = array ('cvt ', 'fpgm');
		foreach($tags AS $tag) { 	// 1.02
			if (isset($this->table['tag'])) { $this->add($tag, $this->get_table($tag)); }
		}

		// post - PostScript
		$opost = $this->get_table('post');
		$post = "\x00\x03\x00\x00" . substr($opost,4,12) . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
		$this->add('post', $post);

		// hhea - Horizontal Header
		$hhea = $this->get_table('hhea');
		$hhea = $this->_set_ushort($hhea, 34, $numberOfHMetrics);
		$this->add('hhea', $hhea);

		// maxp - Maximum Profile
		$maxp = $this->get_table('maxp');
		$maxp = $this->_set_ushort($maxp, 4, $numGlyphs);
		$this->add('maxp', $maxp);

		// cmap - Character to glyph mapping - Format 6
		$entryCount = count($subset);
		$length = 10 + $entryCount * 2;
		$cmap = array(0, 1,	// Index : version, number of subtables
			1, 0,			// Subtable : platform, encoding
			0, 12,		// offset (hi,lo)
			6, $length, 	// Format 6 Mapping table: format, length
			0, 1,			// language, First char code
			$entryCount);

		foreach($subset AS $code) { $cmap[] = $codeToGlyph[$code]; }
		$cmapstr = '';
		foreach($cmap AS $cm) { $cmapstr .= pack("n",$cm); }
		$this->add('cmap', $cmapstr);

/*
		// cmap - Character to glyph mapping - Format 4 (MS / )
		$segCount = 2;
		$searchRange = 1;
		$entrySelector = 0;
		while ($searchRange * 2 <= $segCount ) {
			$searchRange = $searchRange * 2;
			$entrySelector = $entrySelector + 1;
		}
		$searchRange = $searchRange * 16;
		$rangeShift = $segCount * 16 - $searchRange;
		$length = 48 + ($numGlyphs-1);
		$cmap = array(0, 1,	// Index : version, number of subtables
			3, 0,			// Subtable : platform (MS=3), encoding
			0, 12,		// Subtable : offset (hi,lo)
			4, $length, 0, 	// Format 4 Mapping table: format, length, language
			$segCount*2,
			$searchRange,
			$entrySelector,
			$rangeShift,
			$numGlyphs, 0xFFFF,	// endCode(s)
			0,
			1, 0xFFFF,			// startCode(s)
			0, 1,				// idDelta(s) Delta for all character codes in segment
			0, 0); 			// idRangeOffset[segCount]  	Offset in bytes to glyph indexArray, or 0
		foreach($subset AS $code) { $cmap[] = $codeToGlyph[$code]; }
		$cmap[] = 0xFFFF;
		$cmapstr = '';
		foreach($cmap AS $cm) { $cmapstr .= pack("n",$cm); }
		$this->add('cmap', $cmapstr);
*/


		// hmtx - Horizontal Metrics
		$hmtxstr = '';
		for($n=0;$n<$numGlyphs;$n++) {
			$originalGlyphIdx = $glyphMap[$n];
			$aw = $this->hmetrics[$originalGlyphIdx][0];
			$lsb = $this->hmetrics[$originalGlyphIdx][1];
			if ($n < $numberOfHMetrics) { $hmtxstr .= pack("n",$aw); }
			$hmtxstr .= $this->pack_short($lsb);
		}
		$this->add('hmtx', $hmtxstr);

		// glyf - Glyph data
		list($glyfOffset,$glyfLength) = $this->get_table_pos('glyf');
		if ($glyfLength < $this->maxStrLenRead) {
			$glyphData = $this->get_table('glyf');
		}

		$offsets = array();
		$glyf = '';
		$pos = 0;

		for ($n=0;$n<$numGlyphs;$n++) {
			$offsets[] = $pos;
			$originalGlyphIdx = $glyphMap[$n];
			$glyphPos = $this->glyphPos[$originalGlyphIdx];
			$glyphLen = $this->glyphPos[$originalGlyphIdx + 1] - $glyphPos;
			if ($glyfLength < $this->maxStrLenRead) {
				$data = substr($glyphData,$glyphPos,$glyphLen);
			}
			else {
				if ($glyphLen > 0) $data = $this->get_chunk($glyfOffset+$glyphPos,$glyphLen);
				else $data = '';
			}
			if ($glyphLen > 0) $up = unpack("n", substr($data,0,2));
			if ($glyphLen > 2 && ($up[1] & (1 << 15)) ) {
				$pos_in_glyph = 10;
				$flags = GF_MORE;
				while ($flags & GF_MORE) {
					$up = unpack("n", substr($data,$pos_in_glyph,2));
					$flags = $up[1];
					$up = unpack("n", substr($data,$pos_in_glyph+2,2));
					$glyphIdx = $up[1];
					$data = $this->_set_ushort($data, $pos_in_glyph + 2, $glyphSet[$glyphIdx]);
					$pos_in_glyph += 4;
					if ($flags & GF_WORDS) { $pos_in_glyph += 4; }
					else { $pos_in_glyph += 2; }
					if ($flags & GF_SCALE) { $pos_in_glyph += 2; }
					else if ($flags & GF_XYSCALE) { $pos_in_glyph += 4; }
					else if ($flags & GF_TWOBYTWO) { $pos_in_glyph += 8; }
				}
			}
			$glyf .= $data;
			$pos += $glyphLen;
			if ($pos % 4 != 0) {
				$padding = 4 - ($pos % 4);
				$glyf .= str_repeat("\0",$padding);
				$pos += $padding;
			}
		}
		$offsets[] = $pos;
		$this->add('glyf', $glyf);

		// loca - Index to location
		$locastr = '';
		if ((($pos + 1) >> 1) > 0xFFFF) {
			$indexToLocFormat = 1;        // long format
			foreach($offsets AS $offset) { $locastr .= pack("N",$offset); }
		}
		else {
			$indexToLocFormat = 0;        // short format
			foreach($offsets AS $offset) { $locastr .= pack("n",($offset/2)); }
		}
		$this->add('loca', $locastr);

		// head - Font header
		$head = $this->get_table('head');
		$head = $this->_set_ushort($head, 50, $indexToLocFormat);
		$this->add('head', $head);

		fclose($this->fh);

		// Put the TTF file together
		$stm = '';
		$numTables = count($this->otables);
		$searchRange = 1;
		$entrySelector = 0;
		while ($searchRange * 2 <= $numTables) {
			$searchRange = $searchRange * 2;
			$entrySelector = $entrySelector + 1;
		}
		$searchRange = $searchRange * 16;
		$rangeShift = $numTables * 16 - $searchRange;

		// Header
		$stm .= (pack("Nnnnn", 0x74727565, $numTables, $searchRange, $entrySelector, $rangeShift));	// 0x74727565 "true" for Mac
		// 0x00010000 for Windows

		// Table directory
		$tables = $this->otables;
		ksort ($tables); 
		$offset = 12 + $numTables * 16;
		foreach ($tables AS $tag=>$data) {
			if ($tag == 'head') { $head_start = $offset; }
			$stm .= $tag;
			$checksum = $this->calcChecksum($data);
			$stm .= pack("nn", $checksum[0],$checksum[1]);
			$stm .= pack("NN", $offset, strlen($data));
			$paddedLength = (strlen($data)+3)&~3;
			$offset = $offset + $paddedLength;
		}

		// Table data
		foreach ($tables AS $tag=>$data) {
			$data .= "\0\0\0";
			$stm .= substr($data,0,(strlen($data)&~3));
		}

		$checksum = $this->calcChecksum($stm);
		$checksum = $this->sub32(array(0xB1B0,0xAFBA), $checksum);
		$chk = pack("nn", $checksum[0],$checksum[1]);
		$stm = $this->splice($stm,($head_start + 8),$chk);
		return $stm ;
	}


}


?>