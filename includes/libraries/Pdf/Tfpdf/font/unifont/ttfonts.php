<?php


/*******************************************************************************
* TTFontFile class                                                             *
*                                                                              *
* This class is based on The ReportLab Open Source PDF library                 *
* written in Python - http://www.reportlab.com/software/opensource/            *
* together with ideas from the OpenOffice source code and others.              *
*                                                                              *
* Version:  1.04                                                               *
* Date:     2011-09-18                                                         *
* Author:   Ian Back <ianb@bpm1.com>                                           *
* License:  LGPL                                                               *
* Copyright (c) Ian Back, 2010                                                 *
* This header must be retained in any redistribution or                        *
* modification of the file.                                                    *
*                                                                              *
*******************************************************************************/

// Define the value used in the "head" table of a created TTF file
// 0x74727565 "true" for Mac
// 0x00010000 for Windows
// Either seems to work for a font embedded in a PDF file
// when read by Adobe Reader on a Windows PC(!)
define("_TTF_MAC_HEADER", false);


// TrueType Font Glyph operators
define("GF_WORDS",(1 << 0));
define("GF_SCALE",(1 << 3));
define("GF_MORE",(1 << 5));
define("GF_XYSCALE",(1 << 6));
define("GF_TWOBYTWO",(1 << 7));



class TTFontFile {

var $maxUni;
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

	function __construct() {
		$this->maxStrLenRead = 200000;	// Maximum size of glyf table to read in as string (otherwise reads each glyph from file)
	}


	function getMetrics($file) {
		$this->filename = $file;
		$this->fh = fopen($file,'rb') or die('Can\'t open file ' . $file);
		$this->_pos = 0;
		$this->charWidths = '';
		$this->glyphPos = array();
		$this->charToGlyph = array();
		$this->tables = array();
		$this->otables = array();
		$this->ascent = 0;
		$this->descent = 0;
		$this->TTCFonts = array();
		$this->version = $version = $this->read_ulong();
		if ($version==0x4F54544F) 
			die("Postscript outlines are not supported");
		if ($version==0x74746366) 
			die("ERROR - TrueType Fonts Collections not supported");
		if (!in_array($version, array(0x00010000,0x74727565)))
			die("Not a TrueType font: version=".$version);
		$this->readTableDirectory();
		$this->extractInfo();
		fclose($this->fh);
	}


	function readTableDirectory() {
	    $this->numTables = $this->read_ushort();
            $this->searchRange = $this->read_ushort();
            $this->entrySelector = $this->read_ushort();
            $this->rangeShift = $this->read_ushort();
            $this->tables = array();	
            for ($i=0;$i<$this->numTables;$i++) {
                $record = array();
                $record['tag'] = $this->read_tag();
                $record['checksum'] = array($this->read_ushort(),$this->read_ushort());
                $record['offset'] = $this->read_ulong();
                $record['length'] = $this->read_ulong();
                $this->tables[$record['tag']] = $record;
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

	function unpack_short($s) {
		$a = (ord($s[0])<<8) + ord($s[1]);
		if ($a & (1 << 15) ) { 
			$a = ($a - (1 << 16)); 
		}
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

	function _set_short($stream, $offset, $val) {
		if ($val<0) { 
			$val = abs($val);
			$val = ~$val;
			$val += 1;
		}
		$up = pack("n",$val); 
		return $this->splice($stream, $offset, $up);
	}

	function get_chunk($pos, $length) {
		fseek($this->fh,$pos);
		if ($length <1) { return ''; }
		return (fread($this->fh,$length));
	}

	function get_table($tag) {
		list($pos, $length) = $this->get_table_pos($tag);
		if ($length == 0) { die('Truetype font ('.$this->filename.'): error reading table: '.$tag); }
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
/////////////////////////////////////////////////////////////////////////////////////////

/////////////////////////////////////////////////////////////////////////////////////////

	function extractInfo() {
		///////////////////////////////////
		// name - Naming table
		///////////////////////////////////
			$this->sFamilyClass = 0;
			$this->sFamilySubClass = 0;

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
						$N .= (chr($char));
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
				$psName = $names[6];
			else if ($names[4])
				$psName = preg_replace('/ /','-',$names[4]);
			else if ($names[1])
				$psName = preg_replace('/ /','-',$names[1]);
			else
				$psName = '';
			if (!$psName)
				die("Could not find PostScript font name");
			$this->name = $psName;
			if ($names[1]) { $this->familyName = $names[1]; } else { $this->familyName = $psName; }
			if ($names[2]) { $this->styleName = $names[2]; } else { $this->styleName = 'Regular'; }
			if ($names[4]) { $this->fullName = $names[4]; } else { $this->fullName = $psName; }
			if ($names[3]) { $this->uniqueFontID = $names[3]; } else { $this->uniqueFontID = $psName; }
			if ($names[6]) { $this->fullName = $names[6]; }

		///////////////////////////////////
		// head - Font header table
		///////////////////////////////////
		$this->seek_table("head");
		$this->skip(18); 
		$this->unitsPerEm = $unitsPerEm = $this->read_ushort();
		$scale = 1000 / $unitsPerEm;
		$this->skip(16);
		$xMin = $this->read_short();
		$yMin = $this->read_short();
		$xMax = $this->read_short();
		$yMax = $this->read_short();
		$this->bbox = array(($xMin*$scale), ($yMin*$scale), ($xMax*$scale), ($yMax*$scale));
		$this->skip(3*2);
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
			if ($fsType == 0x0002 || ($fsType & 0x0300) != 0) {
				die('ERROR - Font file '.$this->filename.' cannot be embedded due to copyright restrictions.');
				$this->restrictedUse = true;
			}
			$this->skip(20);
			$sF = $this->read_short();
			$this->sFamilyClass = ($sF >> 8);
			$this->sFamilySubClass = ($sF & 0xFF);
			$this->_pos += 10;  //PANOSE = 10 byte length
			$panose = fread($this->fh,10);
			$this->skip(26);
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
		$this->skip(4); 
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
		$this->skip(32); 
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
		$this->skip(4); 
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
			if (($platformID == 3 && $encodingID == 1) || $platformID == 0) { // Microsoft, Unicode
				$format = $this->get_ushort($cmap_offset + $offset);
				if ($format == 4) {
					if (!$unicode_cmap_offset) $unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			}
			$this->seek($save_pos );
		}
		if (!$unicode_cmap_offset)
			die('Font ('.$this->filename .') does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)');


		$glyphToChar = array();
		$charToGlyph = array();
		$this->getCMAP4($unicode_cmap_offset, $glyphToChar, $charToGlyph );

		///////////////////////////////////
		// hmtx - Horizontal metrics table
		///////////////////////////////////
		$this->getHMTX($numberOfHMetrics, $numGlyphs, $glyphToChar, $scale);

	}


/////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////


	function makeSubset($file, &$subset) {
		$this->filename = $file;
		$this->fh = fopen($file ,'rb') or die('Can\'t open file ' . $file);
		$this->_pos = 0;
		$this->charWidths = '';
		$this->glyphPos = array();
		$this->charToGlyph = array();
		$this->tables = array();
		$this->otables = array();
		$this->ascent = 0;
		$this->descent = 0;
		$this->skip(4);
		$this->maxUni = 0;
		$this->readTableDirectory();


		///////////////////////////////////
		// head - Font header table
		///////////////////////////////////
		$this->seek_table("head");
		$this->skip(50); 
		$indexToLocFormat = $this->read_ushort();
		$glyphDataFormat = $this->read_ushort();

		///////////////////////////////////
		// hhea - Horizontal header table
		///////////////////////////////////
		$this->seek_table("hhea");
		$this->skip(32); 
		$metricDataFormat = $this->read_ushort();
		$orignHmetrics = $numberOfHMetrics = $this->read_ushort();

		///////////////////////////////////
		// maxp - Maximum profile table
		///////////////////////////////////
		$this->seek_table("maxp");
		$this->skip(4);
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
			if (($platformID == 3 && $encodingID == 1) || $platformID == 0) { // Microsoft, Unicode
				$format = $this->get_ushort($cmap_offset + $offset);
				if ($format == 4) {
					$unicode_cmap_offset = $cmap_offset + $offset;
					break;
				}
			}
			$this->seek($save_pos );
		}

		if (!$unicode_cmap_offset)
			die('Font ('.$this->filename .') does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)');


		$glyphToChar = array();
		$charToGlyph = array();
		$this->getCMAP4($unicode_cmap_offset, $glyphToChar, $charToGlyph );

		$this->charToGlyph = $charToGlyph;

		///////////////////////////////////
		// hmtx - Horizontal metrics table
		///////////////////////////////////
		$scale = 1;	// not used
		$this->getHMTX($numberOfHMetrics, $numGlyphs, $glyphToChar, $scale);

		///////////////////////////////////
		// loca - Index to location
		///////////////////////////////////
		$this->getLOCA($indexToLocFormat, $numGlyphs);

		$subsetglyphs = array(0=>0); 
		$subsetCharToGlyph = array();
		foreach($subset AS $code) {
			if (isset($this->charToGlyph[$code])) {
				$subsetglyphs[$this->charToGlyph[$code]] = $code;	// Old Glyph ID => Unicode
				$subsetCharToGlyph[$code] = $this->charToGlyph[$code];	// Unicode to old GlyphID

			}
			$this->maxUni = max($this->maxUni, $code);
		}

		list($start,$dummy) = $this->get_table_pos('glyf');

		$glyphSet = array();
		ksort($subsetglyphs);
		$n = 0;
		$fsLastCharIndex = 0;	// maximum Unicode index (character code) in this font, according to the cmap subtable for platform ID 3 and platform- specific encoding ID 0 or 1.
		foreach($subsetglyphs AS $originalGlyphIdx => $uni) {
			$fsLastCharIndex = max($fsLastCharIndex , $uni);
			$glyphSet[$originalGlyphIdx] = $n;	// old glyphID to new glyphID
			$n++;
		}

		ksort($subsetCharToGlyph);
		foreach($subsetCharToGlyph AS $uni => $originalGlyphIdx) {
			$codeToGlyph[$uni] = $glyphSet[$originalGlyphIdx] ;
		}
		$this->codeToGlyph = $codeToGlyph;

		ksort($subsetglyphs);
		foreach($subsetglyphs AS $originalGlyphIdx => $uni) {
			$this->getGlyphs($originalGlyphIdx, $start, $glyphSet, $subsetglyphs);
		}

		$numGlyphs = $numberOfHMetrics = count($subsetglyphs );

		//tables copied from the original
		$tags = array ('name');
		foreach($tags AS $tag) { $this->add($tag, $this->get_table($tag)); }
		$tags = array ('cvt ', 'fpgm', 'prep', 'gasp');
		foreach($tags AS $tag) {
			if (isset($this->tables[$tag])) { $this->add($tag, $this->get_table($tag)); }
		}

		// post - PostScript
		$opost = $this->get_table('post');
		$post = "\x00\x03\x00\x00" . substr($opost,4,12) . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
		$this->add('post', $post);

		// Sort CID2GID map into segments of contiguous codes
		ksort($codeToGlyph);
		unset($codeToGlyph[0]);
		//unset($codeToGlyph[65535]);
		$rangeid = 0;
		$range = array();
		$prevcid = -2;
		$prevglidx = -1;
		// for each character
		foreach ($codeToGlyph as $cid => $glidx) {
			if ($cid == ($prevcid + 1) && $glidx == ($prevglidx + 1)) {
				$range[$rangeid][] = $glidx;
			} else {
				// new range
				$rangeid = $cid;
				$range[$rangeid] = array();
				$range[$rangeid][] = $glidx;
			}
			$prevcid = $cid;
			$prevglidx = $glidx;
		}

		// cmap - Character to glyph mapping - Format 4 (MS / )
		$segCount = count($range) + 1;	// + 1 Last segment has missing character 0xFFFF
		$searchRange = 1;
		$entrySelector = 0;
		while ($searchRange * 2 <= $segCount ) {
			$searchRange = $searchRange * 2;
			$entrySelector = $entrySelector + 1;
		}
		$searchRange = $searchRange * 2;
		$rangeShift = $segCount * 2 - $searchRange;
		$length = 16 + (8*$segCount ) + ($numGlyphs+1);
		$cmap = array(0, 1,		// Index : version, number of encoding subtables
			3, 1,				// Encoding Subtable : platform (MS=3), encoding (Unicode)
			0, 12,			// Encoding Subtable : offset (hi,lo)
			4, $length, 0, 		// Format 4 Mapping subtable: format, length, language
			$segCount*2,
			$searchRange,
			$entrySelector,
			$rangeShift);

		// endCode(s)
		foreach($range AS $start=>$subrange) {
			$endCode = $start + (count($subrange)-1);
			$cmap[] = $endCode;	// endCode(s)
		}
		$cmap[] =	0xFFFF;	// endCode of last Segment
		$cmap[] =	0;	// reservedPad

		// startCode(s)
		foreach($range AS $start=>$subrange) {
			$cmap[] = $start;	// startCode(s)
		}
		$cmap[] =	0xFFFF;	// startCode of last Segment
		// idDelta(s) 
		foreach($range AS $start=>$subrange) {
			$idDelta = -($start-$subrange[0]);
			$n += count($subrange);
			$cmap[] = $idDelta;	// idDelta(s)
		}
		$cmap[] =	1;	// idDelta of last Segment
		// idRangeOffset(s) 
		foreach($range AS $subrange) {
			$cmap[] = 0;	// idRangeOffset[segCount]  	Offset in bytes to glyph indexArray, or 0

		}
		$cmap[] =	0;	// idRangeOffset of last Segment
		foreach($range AS $subrange) {
			foreach($subrange AS $glidx) {
				$cmap[] = $glidx;
			}
		}
		$cmap[] = 0;	// Mapping for last character
		$cmapstr = '';
		foreach($cmap AS $cm) { $cmapstr .= pack("n",$cm); }
		$this->add('cmap', $cmapstr);


		// glyf - Glyph data
		list($glyfOffset,$glyfLength) = $this->get_table_pos('glyf');
		if ($glyfLength < $this->maxStrLenRead) {
			$glyphData = $this->get_table('glyf');
		}

		$offsets = array();
		$glyf = '';
		$pos = 0;

		$hmtxstr = '';
		$xMinT = 0;
		$yMinT = 0;
		$xMaxT = 0;
		$yMaxT = 0;
		$advanceWidthMax = 0;
		$minLeftSideBearing = 0;
		$minRightSideBearing = 0;
		$xMaxExtent = 0;
		$maxPoints = 0;			// points in non-compound glyph
		$maxContours = 0;			// contours in non-compound glyph
		$maxComponentPoints = 0;	// points in compound glyph
		$maxComponentContours = 0;	// contours in compound glyph
		$maxComponentElements = 0;	// number of glyphs referenced at top level
		$maxComponentDepth = 0;		// levels of recursion, set to 0 if font has only simple glyphs
		$this->glyphdata = array();

		foreach($subsetglyphs AS $originalGlyphIdx => $uni) {
			// hmtx - Horizontal Metrics
			$hm = $this->getHMetric($orignHmetrics, $originalGlyphIdx);	
			$hmtxstr .= $hm;

			$offsets[] = $pos;
			$glyphPos = $this->glyphPos[$originalGlyphIdx];
			$glyphLen = $this->glyphPos[$originalGlyphIdx + 1] - $glyphPos;
			if ($glyfLength < $this->maxStrLenRead) {
				$data = substr($glyphData,$glyphPos,$glyphLen);
			}
			else {
				if ($glyphLen > 0) $data = $this->get_chunk($glyfOffset+$glyphPos,$glyphLen);
				else $data = '';
			}

			if ($glyphLen > 0) {
				$up = unpack("n", substr($data,0,2));
			}

			if ($glyphLen > 2 && ($up[1] & (1 << 15)) ) {	// If number of contours <= -1 i.e. composiste glyph
				$pos_in_glyph = 10;
				$flags = GF_MORE;
				$nComponentElements = 0;
				while ($flags & GF_MORE) {
					$nComponentElements += 1;	// number of glyphs referenced at top level
					$up = unpack("n", substr($data,$pos_in_glyph,2));
					$flags = $up[1];
					$up = unpack("n", substr($data,$pos_in_glyph+2,2));
					$glyphIdx = $up[1];
					$this->glyphdata[$originalGlyphIdx]['compGlyphs'][] = $glyphIdx;
					$data = $this->_set_ushort($data, $pos_in_glyph + 2, $glyphSet[$glyphIdx]);
					$pos_in_glyph += 4;
					if ($flags & GF_WORDS) { $pos_in_glyph += 4; }
					else { $pos_in_glyph += 2; }
					if ($flags & GF_SCALE) { $pos_in_glyph += 2; }
					else if ($flags & GF_XYSCALE) { $pos_in_glyph += 4; }
					else if ($flags & GF_TWOBYTWO) { $pos_in_glyph += 8; }
				}
				$maxComponentElements = max($maxComponentElements, $nComponentElements);
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

		// hmtx - Horizontal Metrics
		$this->add('hmtx', $hmtxstr);

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


		// hhea - Horizontal Header
		$hhea = $this->get_table('hhea');
		$hhea = $this->_set_ushort($hhea, 34, $numberOfHMetrics);
		$this->add('hhea', $hhea);

		// maxp - Maximum Profile
		$maxp = $this->get_table('maxp');
		$maxp = $this->_set_ushort($maxp, 4, $numGlyphs);
		$this->add('maxp', $maxp);


		// OS/2 - OS/2
		$os2 = $this->get_table('OS/2');
		$this->add('OS/2', $os2 );

		fclose($this->fh);

		// Put the TTF file together
		$stm = '';
		$this->endTTFile($stm);
		return $stm ;
	}

	//////////////////////////////////////////////////////////////////////////////////
	// Recursively get composite glyph data
	function getGlyphData($originalGlyphIdx, &$maxdepth, &$depth, &$points, &$contours) {
		$depth++;
		$maxdepth = max($maxdepth, $depth);
		if (count($this->glyphdata[$originalGlyphIdx]['compGlyphs'])) {
			foreach($this->glyphdata[$originalGlyphIdx]['compGlyphs'] AS $glyphIdx) {
				$this->getGlyphData($glyphIdx, $maxdepth, $depth, $points, $contours);
			}
		}
		else if (($this->glyphdata[$originalGlyphIdx]['nContours'] > 0) && $depth > 0) {	// simple
			$contours += $this->glyphdata[$originalGlyphIdx]['nContours'];
			$points += $this->glyphdata[$originalGlyphIdx]['nPoints'];
		}
		$depth--;
	}


	//////////////////////////////////////////////////////////////////////////////////
	// Recursively get composite glyphs
	function getGlyphs($originalGlyphIdx, &$start, &$glyphSet, &$subsetglyphs) {
		$glyphPos = $this->glyphPos[$originalGlyphIdx];
		$glyphLen = $this->glyphPos[$originalGlyphIdx + 1] - $glyphPos;
		if (!$glyphLen) { 
			return;
		}
		$this->seek($start + $glyphPos);
		$numberOfContours = $this->read_short();
		if ($numberOfContours < 0) {
			$this->skip(8);
			$flags = GF_MORE;
			while ($flags & GF_MORE) {
				$flags = $this->read_ushort();
				$glyphIdx = $this->read_ushort();
				if (!isset($glyphSet[$glyphIdx])) {
					$glyphSet[$glyphIdx] = count($subsetglyphs);	// old glyphID to new glyphID
					$subsetglyphs[$glyphIdx] = true;
				}
				$savepos = ftell($this->fh);
				$this->getGlyphs($glyphIdx, $start, $glyphSet, $subsetglyphs);
				$this->seek($savepos);
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

	//////////////////////////////////////////////////////////////////////////////////

	function getHMTX($numberOfHMetrics, $numGlyphs, &$glyphToChar, $scale) {
		$start = $this->seek_table("hmtx");
		$aw = 0;
		$this->charWidths = str_pad('', 256*256*2, "\x00");
		$nCharWidths = 0;
		if (($numberOfHMetrics*4) < $this->maxStrLenRead) {
			$data = $this->get_chunk($start,($numberOfHMetrics*4));
			$arr = unpack("n*", $data);
		}
		else { $this->seek($start); }
		for( $glyph=0; $glyph<$numberOfHMetrics; $glyph++) {

			if (($numberOfHMetrics*4) < $this->maxStrLenRead) {
				$aw = $arr[($glyph*2)+1];
			}
			else {
				$aw = $this->read_ushort();
				$lsb = $this->read_ushort();
			}
			if (isset($glyphToChar[$glyph]) || $glyph == 0) {

				if ($aw >= (1 << 15) ) { $aw = 0; }	// 1.03 Some (arabic) fonts have -ve values for width
					// although should be unsigned value - comes out as e.g. 65108 (intended -50)
				if ($glyph == 0) {
					$this->defaultWidth = $scale*$aw;
					continue;
				}
				foreach($glyphToChar[$glyph] AS $char) {
					if ($char != 0 && $char != 65535) {
 						$w = intval(round($scale*$aw));
						if ($w == 0) { $w = 65535; }
						if ($char < 196608) {
							$this->charWidths[$char*2] = chr($w >> 8);
							$this->charWidths[$char*2 + 1] = chr($w & 0xFF);
							$nCharWidths++;
						}
					}
				}
			}
		}
		$data = $this->get_chunk(($start+$numberOfHMetrics*4),($numGlyphs*2));
		$arr = unpack("n*", $data);
		$diff = $numGlyphs-$numberOfHMetrics;
		for( $pos=0; $pos<$diff; $pos++) {
			$glyph = $pos + $numberOfHMetrics;
			if (isset($glyphToChar[$glyph])) {
				foreach($glyphToChar[$glyph] AS $char) {
					if ($char != 0 && $char != 65535) {
						$w = intval(round($scale*$aw));
						if ($w == 0) { $w = 65535; }
						if ($char < 196608) {
							$this->charWidths[$char*2] = chr($w >> 8);
							$this->charWidths[$char*2 + 1] = chr($w & 0xFF);
							$nCharWidths++;
						}
					}
				}
			}
		}
		// NB 65535 is a set width of 0
		// First bytes define number of chars in font
		$this->charWidths[0] = chr($nCharWidths >> 8);
		$this->charWidths[1] = chr($nCharWidths & 0xFF);
	}

	function getHMetric($numberOfHMetrics, $gid) {
		$start = $this->seek_table("hmtx");
		if ($gid < $numberOfHMetrics) {
			$this->seek($start+($gid*4));
			$hm = fread($this->fh,4);
		}
		else {
			$this->seek($start+(($numberOfHMetrics-1)*4));
			$hm = fread($this->fh,2);
			$this->seek($start+($numberOfHMetrics*2)+($gid*2));
			$hm .= fread($this->fh,2);
		}
		return $hm;
	}

	function getLOCA($indexToLocFormat, $numGlyphs) {
		$start = $this->seek_table('loca');
		$this->glyphPos = array();
		if ($indexToLocFormat == 0) {
			$data = $this->get_chunk($start,($numGlyphs*2)+2);
			$arr = unpack("n*", $data);
			for ($n=0; $n<=$numGlyphs; $n++) {
				$this->glyphPos[] = ($arr[$n+1] * 2);
			}
		}
		else if ($indexToLocFormat == 1) {
			$data = $this->get_chunk($start,($numGlyphs*4)+4);
			$arr = unpack("N*", $data);
			for ($n=0; $n<=$numGlyphs; $n++) {
				$this->glyphPos[] = ($arr[$n+1]);
			}
		}
		else 
			die('Unknown location table format '.$indexToLocFormat);
	}


	// CMAP Format 4
	function getCMAP4($unicode_cmap_offset, &$glyphToChar, &$charToGlyph ) {
		$this->maxUniChar = 0;
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

		for ($n=0;$n<$segCount;$n++) {
			$endpoint = ($endCount[$n] + 1);
			for ($unichar=$startCount[$n];$unichar<$endpoint;$unichar++) {
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
				$charToGlyph[$unichar] = $glyph;
				if ($unichar < 196608) { $this->maxUniChar = max($unichar,$this->maxUniChar); }
				$glyphToChar[$glyph][] = $unichar;
			}
		}
	}


		// Put the TTF file together
	function endTTFile(&$stm) {
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
		if (_TTF_MAC_HEADER) {
			$stm .= (pack("Nnnnn", 0x74727565, $numTables, $searchRange, $entrySelector, $rangeShift));	// Mac
		}
		else {
			$stm .= (pack("Nnnnn", 0x00010000 , $numTables, $searchRange, $entrySelector, $rangeShift));	// Windows
		}

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