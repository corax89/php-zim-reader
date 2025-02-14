<?php
$file_name = "wikipedia_ru_top_maxi_2024-06.zim";
$fileNotFound = "<h3>files not found</h3>";
$shortLine = "<h3>Enter a word longer than 2 letters</h3>";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mb_internal_encoding("UTF-8");

$search   = -1;
$get_file = -1;

if(isset($_GET["s"]))
	$search = $_GET["s"];

if(isset($_GET["f"]))
	$get_file = $_GET["f"];

if(isset($_GET["p"]))
	$get_file = $_GET["p"];

$find_field = "<form action=\"index.php\" method=\"get\" id=\"searchInput\" style=\"margin-left:auto;margin-right:auto;margin-top:1em;max-width:35em;position:relative;padding:.2em;z-index:999;\">" .
            "<label><input type=\"text\" value=\"" . ($search==-1?($get_file==-1?"":$get_file):$search) . "\" style=\"width:100%;background:#f9e2c3;border:solid 1px #0a243c;height:1.7em;padding:0;" .
            "padding-left:8px;font-size:1em;box-shadow: 0px 5px 10px 0px rgba(0,0,0,0.5);\" name=\"s\"></label><button type=\"submit\" style=\"position:absolute;border:0;background-color:transparent;" .
            "padding:0;right:0;top:5px;\"><img src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAQAAABKfvVzAAAA5ElEQVR42sXSoXbCMAAF0GtmojBR/QIcEhNXyQ9MYdGVdfwPoufwAcjauIqZKhwuDrEZDFvGqdqu" .
            "f+8lOfEP1lpJtMCbzqyYzO4ukpeiUbYTBES94viqfXQSdGafEli76qjrZMFg9q4BkBSNqtlOZ7bybKgfa60IJnvf7Y0qWpPgXplPriqSGU216kNFdBfV9M6qLnpk4fmxq/cCSbEWfvRPAnVHV9un9l6x8UKnOHm3lXQmk43Bwf73lcbR6OrD2V5Ap8" .
            "iylYUOjy+TRYusZKdHJFgkygbBTcvyyM1NtFjQiv7IF7V8R5OI0NPRAAAAAElFTkSuQmCC\" style=\"position:absolute;right:.3em;\"></button></form><br>";

if($search != -1){
	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">";
	echo "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1.0\">";
	echo "<body style=\"margin:0;padding:0;border:0;\">";
	echo $find_field;
}

function print404(){
    global $get_file, $find_field;
    echo "<body style=\"text-align: center;\">";
    echo $get_file;
    echo "<h1>404</h1>";
    echo $find_field;
    exit();
}

function readInt64($f){
    if(PHP_INT_SIZE == 4 ){
        $data = fread($f, 4);
        $temp = unpack("I", $data);
        $data = fread($f, 4);
    }
    else{
        $data = fread($f, 8);
        $temp = unpack("Q", $data);
    }
    return $temp[1];
}

function readInt32($f){
	$data = fread($f, 4);
	$temp = unpack("I", $data);
    return $temp[1];
}

function mb_ucfirst_p(string $str): string{
    return mb_strtoupper(mb_substr($str, 0, 1, mb_internal_encoding()), mb_internal_encoding()) . mb_substr($str, 1, null, mb_internal_encoding());
}

$FILE = fopen("./" . $file_name, "rb");

//
// read and load HEADER into «file.zim»
//
$header = array();
fseek($FILE, 0, SEEK_SET); // no necesary because it must be it
$header["magicNumber"] = readInt32($FILE); //ZIM\x04
$header["version"] = readInt32($FILE);
$data = fread($FILE, 16);
$temp = unpack("H*", $data);
$header["uuid"] = $temp[1];
$header["articleCount"] = readInt32($FILE);
$header["clusterCount"] = readInt32($FILE);
$header["urlPtrPos"] = readInt64($FILE);
$header["titlePtrPos"] = readInt64($FILE);
$header["clusterPtrPos"] = readInt64($FILE);
$header["mimeListPos"] = readInt64($FILE);
$header["mainPage"] = readInt32($FILE);
$header["layoutPage"] = readInt32($FILE);
$header["checksumPos"] = readInt64($FILE);

//
// read and load MIME TYPE LIST into «file.zim»
//
$mime = array();
fseek($FILE, $header["mimeListPos"], SEEK_SET); // no necesary because it must be it
// Set delimiter to "\x00"
while (true) {
    // Read a null terminated string from the file
    $b = stream_get_line($FILE, 4096, "\x00");
    // chop($b);
    $b = rtrim($b, "\n\r");
    if ($b === "") {
        break;
    }
    $mime[] = $b;
}

//
// read ARTICLE NUMBER (sort  by url) into URL POINTER LIST into «file.zim»
// return ARTICLE NUMBER POINTER
//
function url_pointer($article) {
    global $header, $FILE;
    if ($article >= $header["articleCount"]) {
        print404();
    }
    $pos = $header["urlPtrPos"];
    $pos += $article * 8;
    fseek($FILE, $pos, SEEK_SET);
    $ret = readInt64($FILE);
    return $ret;
}

//
// no used
// read ARTICLE NUMBER (sort by title) into TITLE POINTER LIST into «file.zim»
// return ARTICLE NUMBER (not pointer)
function title_pointer($article_by_title) {
    global $header, $FILE;
    if ($article_by_title >= $header["articleCount"]) {
        print404();
    }
    $pos = $header["titlePtrPos"];
    $pos += $article_by_title * 4;
    fseek($FILE, $pos, SEEK_SET);
    $ret = readInt32($FILE);
    return $ret;
}

//
// read ARTICLE NUMBER into «file.zim»
// load ARTICLE ENTRY that is point by ARTICLE NUMBER POINTER
// or load REDIRECT ENTRY
/*
mimetype 	integer 	0 	2 	MIME type number as defined in the MIME type list
parameter len 	byte 	2 	1 	(not used) length of extra paramters (must be 0)
namespace 	char 	3 	1 	defines to which namespace this directory entry belongs
revision 	integer 	4 	4 	(not used) identifies a revision of the contents of this directory entry, needed to identify updates or revisions in the original history (must be 0)

    cluster number 	integer 	8 	4 	cluster number in which the data of this directory entry is stored
    blob number 	integer 	12 	4 	blob number inside the compressed cluster where the contents are stored
or
    redirect index 	integer 	8 	4 	pointer to the directory entry of the redirect target 
path 	string 	16 	zero terminated 	string with the path as referred in the path pointer list
title 	string 	n/a 	zero terminated 	string with an title as referred in the Title pointer list or empty; in case it is empty, the path is used as title
parameter 	data 		see parameter len 	(not used) extra parameters 
*/
$article = array();
function entry($articleNo) {
    global $FILE, $header, $article;
    
    $article = array();
    $article["number"] = $articleNo;
    $pos = url_pointer($articleNo);
    fseek($FILE, $pos, SEEK_SET);
    
    $data = fread($FILE, 2);
    $temp = unpack("s", $data);
    $article["mimetype"] = $temp[1];

    $data = fread($FILE, 1);
    $temp = unpack("H*", $data);
    $article["parameter_len"] = hexdec($temp[1]);

    $data = fread($FILE, 1);
    $temp = unpack("a", $data);
    $article["namespace"] = $temp[1];

    $article["revision"] = readInt32($FILE);

    if ($article["mimetype"] < 0) {
        $article["redirect_index"] = readInt32($FILE);
    } else {
        $article["cluster_number"] = readInt32($FILE);
        $article["blob_number"] = readInt32($FILE);
    }
    // Set record separator to "\x00"
    $article["url"] = stream_get_line($FILE, 4096, "\x00");
    $article["title"] = stream_get_line($FILE, 4096, "\x00");
    // chop the last character off each
    $article["url"] = rtrim($article["url"], "\n\r");
    $article["title"] = rtrim($article["title"], "\n\r");
}

//
// read CLUSTER NUMBER into CLUSTER POINTER LIST into «file.zim»
// return CLUSTER NUMBER POINTER
//
function cluster_pointer($cluster) {
    global $header, $FILE;
    
    if ($cluster >= $header["clusterCount"]) {
        return $header["checksumPos"];
    }
    $pos = $header["clusterPtrPos"] + $cluster * 8;
    fseek($FILE, $pos, SEEK_SET);
    $ret = readInt64($FILE);
    
    return $ret;
}

//
// read CLUSTER NUMBER into «file.zim»
// decompress CLUSTER
// read BLOB NUMBER into «CLUSTER»
// return DATA
//
function cluster_blob($cluster, $blob) {
    global $FILE, $argv;
    
    $pos = cluster_pointer($cluster);
    $nextPos = cluster_pointer($cluster + 1);
    $size = $nextPos - $pos - 1;
    fseek($FILE, $pos, SEEK_SET);
    $clusterData = array();
    $data = fread($FILE, 1);
    $temp = unpack("C", $data);
    $clusterData["compression_type"] = $temp[1] & 0x0f;
    
    if ($clusterData["compression_type"] == 4) {
        $data_compressed = fread($FILE, $size);
        $file = "/tmp/wikimirror_cluster" . $cluster . "-pid" . getmypid();
        $file_xz = $file . ".xz";
        $DATA = fopen($file_xz, "wb");
        fwrite($DATA, $data_compressed);
        fclose($DATA);
        // Execute xz -d -f $file.xz
        shell_exec("xz -d -f " . escapeshellarg($file_xz));
        $DATA = fopen($file, "rb");
        fseek($DATA, $blob * 4, SEEK_SET);
        $posStart = readInt32($DATA);
        $posEnd = readInt32($DATA);
        fseek($DATA, $posStart, SEEK_SET);
        $ret = fread($DATA, $posEnd - $posStart);
        fclose($DATA);
        shell_exec("rm " . escapeshellarg($file));
    } 
    else if ($clusterData["compression_type"] == 5) {
        $data_compressed = fread($FILE, $size);
        $file = "/tmp/wikimirror_cluster" . $cluster . "-pid" . getmypid();
        $file_zstd = $file . ".zstd";
        $file_txt = $file . ".txt";
        $DATA = fopen($file_zstd, "wb");
        fwrite($DATA, $data_compressed);
        fclose($DATA);
        // Execute zstd -d --decompress -o $file.xz
        shell_exec("zstd --decompress -o " . escapeshellarg($file_txt) . " " . escapeshellarg($file_zstd));
        $DATA = fopen($file_txt, "rb");
        fseek($DATA, $blob * 4, SEEK_SET);
        $posStart = readInt32($DATA);
        $posEnd = readInt32($DATA);
        fseek($DATA, $posStart, SEEK_SET);
        $ret = fread($DATA, $posEnd - $posStart);
        fclose($DATA);
        shell_exec("rm " . escapeshellarg($file_zstd));
        shell_exec("rm " . escapeshellarg($file_txt));
    }
    else {
        fseek($FILE, $pos + 1 + $blob * 4, SEEK_SET);
        $posStart = readInt32($FILE);
        $posEnd = readInt32($FILE);fseek($FILE, cluster_pointer($cluster) + $posStart + 1, SEEK_SET);
        $ret = fread($FILE, $posEnd - $posStart);
    }
    
    return $ret;
}

//
// read ARTICLE NUMBER into «file.zim»
// return DATA
//
function output_articleNumber($articleNumber) {
    global $article;
    while (true) {
        entry($articleNumber);
        if (isset($article["redirect_index"])) {
            $articleNumber = $article["redirect_index"];
        } else {
            return cluster_blob($article["cluster_number"], $article["blob_number"]);
            break;
        }
    }
}

function output_articleList($url){
    global $header, $article, $mime, $argv, $search, $fileNotFound;
    
    entry($header["mainPage"]);
    $namespace = $article["namespace"];
    $articleNumberAbove = $header["articleCount"];
    $articleNumberBelow = 0;
    $articleCount = 0;
    
    $url = $namespace . $url;
    
    while (true) {
        $articleNumber = intval(($articleNumberAbove + $articleNumberBelow) / 2);
        entry($articleNumber);
        if ( $article["namespace"] . $article["url"] > $url) {
            $articleNumberAbove = $articleNumber - 1;
        } elseif ( $article["namespace"] . $article["url"] < $url ) {
            $articleNumberBelow = $articleNumber + 1;
        }
        if(substr($article["namespace"] . $article["url"], 0, strlen($url)) == $url){
            while(substr($article["namespace"] . $article["url"], 0, strlen($url)) == $url){
                $articleNumber--;
                entry($articleNumber);
            }
            $articleNumber++;
            entry($articleNumber);
            while(substr($article["namespace"] . $article["url"], 0, strlen($url)) == $url){
                if(strlen($article["title"]) <= 1)
                    echo "<a href='index.php?f=" . $article["url"] ."' style=\"font-size:1.6em;line-height:2em;\">" .$article["url"] . "</a><br>";
                else
                    echo "<a href='index.php?f=" . $article["url"] ."' style=\"font-size:1.6em;line-height:2em;\">" .$article["title"] . "</a><br>";
                echo "<iframe src=\"index.php?p=" . $article["url"] ."\" onload=\"this.before((this.contentDocument.body||this.contentDocument).children[0]);this.remove()\" frameborder=\"0\" border=\"0\" cellspacing=\"0\" style=\"border-style:none;width:100%;height:120px;\"></iframe>";
                $articleNumber++;
                $articleCount++;
                entry($articleNumber); 
            }
            return $articleCount;
        }
        if ($articleNumberAbove < $articleNumberBelow) {
            return 0;
        }
    }
}

//
// search url into «file.zim»
// return number
//
function output_article($namespace, $url) {
    global $header, $article, $mime, $argv, $search, $get_img;
    $articleNumberAbove = $header["articleCount"];
    $articleNumberBelow = 0;
    
    $url = $namespace . $url;
    
    while (true) {
        $articleNumber = intval(($articleNumberAbove + $articleNumberBelow) / 2);
        entry($articleNumber);
        if ( $article["namespace"] . $article["url"] > $url) {
            $articleNumberAbove = $articleNumber - 1;
        } elseif ( $article["namespace"] . $article["url"] < $url ) {
            $articleNumberBelow = $articleNumber + 1;
        } else {
            break;
        }
        if ($articleNumberAbove < $articleNumberBelow) {
            return -1;
        }
    }
    return $articleNumber;
}

$namespace;
$path;
$extansion;
function split_addr($str){
	global $namespace, $path, $extansion, $article, $header;
	entry($header["mainPage"]);
	$namespace = $article["namespace"];
	$path = "";
	$i = 0;
	while($str[$i] == " ")
		$i++;
	if(substr($str, 0, 3) == "../"){
		while(substr($str, $i, 3) == "../")
			$i += 3;
		if($str[$i + 1] == "/"){
			$namespace = $str[$i];
			$i += 2;
		}
	}
	if($str[$i] == "/")
		$i++;
	$path = substr($str, $i);
	$i = strlen($path) - 1;
	while($i > 0 && $path[$i] != ".")
	    $i--;
	$extansion = substr($path, $i + 1);
}

if($search != -1){
    echo "<div style=\"margin-left:auto;margin-right:auto;margin-top:1em;max-width:35em;line-height:1.6em;padding:0.2em;\">";
    $c = output_articleList($search);
    if(mb_strlen($search) > 2){
        if(mb_ucfirst_p($search) != $search)
            $c = $c + output_articleList(mb_ucfirst_p($search));
        if(mb_strtoupper($search) != $search)
            $c = $c + output_articleList(mb_strtoupper($search));
        if($c <= 0)
            print $fileNotFound;
    }
    else
        print $shortLine;
    echo "<hr></div>";
    exit;
}
else if($get_file != -1){
    split_addr($get_file);
    $out_a = output_article($namespace, $path);
    if($out_a >= 0)
        $out_a = output_articleNumber($out_a);
    else
        print404();
    
    switch(strtolower($extansion)){
        case "png":
        case "gif":
        case "jpg":
        case "jpeg":
        case "webp":
        case "svg":
            $out_type = "image";
            break;
        case "js":
        case "css":
            $out_type = "text";
            break;
        default:
            $out_type = "html";
    }
    if($out_type != "html"){
        $type = $out_type . "/" . strtolower($extansion);
        header('Content-Type:'.$type);
        header('Content-Length: ' . strlen($out_a));
        header('Content-Disposition: attachment; filename=' . basename($path));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $out_a;
    }
    else {
    	if(isset($_GET["p"])){
    	    $out_a = "<div>" . mb_substr(strip_tags($out_a), 0, 400) . "...</div>";
    	}
    	else{
            $out_a = preg_replace('/(href *= *" *)([^#|^http])/', 'href="index.php?f=$2', $out_a);
            $out_a = preg_replace('/(src *= *" *)([^http])/', 'src="index.php?f=$2', $out_a);
            $out_a = preg_replace('/(<body[^>]*>)/', "$1" . $find_field, $out_a, 1);
    	}
        echo $out_a;
    }
}
else{
    $out_a = output_articleNumber($header["mainPage"]);
    $out_a = preg_replace('/(href *= *" *)([^#|^http])/', 'href="index.php?f=$2', $out_a);
    $out_a = preg_replace('/(src *= *" *)([^http])/', 'src="index.php?f=$2', $out_a);
    $out_a = preg_replace('/(<body[^>]*>)/', "$1" . $find_field, $out_a, 1);
    echo $out_a;
}
?>