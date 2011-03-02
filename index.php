<?PHP
//supply a URL
$url = null;
if (!isset($argv)) {
    if (isset($_POST['url'])) {
        $url = $_POST['url']; //MASSIVELY DANGEROUS
    }
} else {
    $url = $argv[1];
}
if ($url == null) {
    include("no-file.html");
    exit;
}
if (!preg_match("|\.css(\?.*)?$|i", $url)) {
    die(".css files only please.");
}
/**
 * Return an array of CSS files imported by the file passed in.
 * @param $css string the complete CSS file being checked
 * @param $url string the URL that $css was fetched from in order to build absolute URLs
 * @return array of absolute URLs
 */
function check_imports(&$css, $url = null) {
    $dir = dirname($url);
    $imports = preg_grep("|import [\'\"](.*)[\'\"];|", preg_split("|\n|", $css));
    if (count($imports) == 0) return $imports;
        $import_urls = array();
    foreach($imports as $import) {
        //REMOVE IMPORTS as they break the names
        $css = str_replace($import, "", $css);
        preg_match("|[\'\"](.*)[\'\"]|", $import, $matches);
        $import_urls[] = $dir."/".$matches[1];
    }
    return $import_urls;
}
/**
 * Get an image, trying the cache first
 * @param $url string absolute URL of the image
 * @return array url, data, width, height
 */
function get_image($url) {
    $path = str_replace(":/", "", $url);
    $data = "";
    if (file_exists("image_cache/".$path)) {
        $data = file_get_contents("image_cache/".$path);
    } else {
        $data = file_get_contents($url);
        if (!save_image($data, $path)) {
            die("Can't save $url to $path");
        }
    }
    $url = "http://".$_SERVER['SERVER_NAME'].dirname($_SERVER['SCRIPT_URL'])."/image_cache/".$path;
    $img = getimagesize($url);
    $return = array(
        'url'     => $url,
        'data'     => $data,
        'width' => $img[0],
        'height'=> $img[1],
        'path'     => "image_cache/".$path
    );
    return $return;
}
/**
 * Save an image to the cache
 * @param $data image data
 * @param $path location
 * @return boolean
 */
function save_image($data, $path) {
    @mkdir("image_cache/".dirname($path), 0777, true);
    if (file_exists("image_cache/".$path)) {
        return true;
    }
    file_put_contents("image_cache/".$path, $data);
    return true;
}
/**
 * Get an array indexed on style name of the style settings
 * @param $css string the entire CSS file
 * @return array [name] => [styles]
 */
$images = array();
function get_styles($css) {
    global $images;
    preg_match_all("|^((.+)\{(.+)\})|msU", $css, $matches);
    $styles = array();
    foreach($matches[2] as $index => $name) {
        $name = trim($name);
                //Flag up IE hacks
        $hack = false;
        if (stristr($name, "* html")) {
            $name = trim(str_ireplace("* html", "", $name));
            $hack = 'star';
        } else if (stristr($name, "*+html")) {
            $name = trim(str_ireplace("*+html", "", $name));
            $hack = 'starplus';
        }
                $styles[$name] =preg_split("|\n|", trim($matches[3][$index]));
                if ($hack) {
            $styles[$name]['hack'] = $hack;
        }
                foreach($styles[$name] as $style_index => $style) {
            $style = trim($style);
                        //Check for IE8 hacks (in style, not name)
            if (stristr($style, "\\0/;")) {
                $style = trim(str_ireplace("\\0/", "", $style));
                $styles[$name]['hack'] = 'ie8';
            }
                //Remove z-index so the 'view gallery' link is always above
            $style = preg_replace("|z-index:(.*?);|", "", $style);
            if (preg_match("|url\('?(.*?)'?\)|", $style, $urls)) {
                $url = $urls[1];
                //Grr
                $url = str_replace("'", "", $url);
                $img = array(0,0);
                if (preg_match("|^[ht\|f]tp[s]?://|", $url)) {
                    //Get it
                } else if (substr($url,0,1) == '/') {
                    //Get domain
                    $url = domain.$url;
                } else {
                    //Get current location
                    $url = domain.path."/".$url;
                }
                //Get the image, save it in a cache
                $img = get_image($url);
                $images[] = $img['path'];
                if (!$img) {
                    die ("can't get $url");
                }
                //Update to use the cached version
                $search = str_replace(array(domain, path."/"), "", $url);
                $style = str_replace($search, $img['url'], $style);
                $styles[$name]['width'] = "min-width:".$img['width']."px;";
                $styles[$name]['height'] = "min-height:".$img['height']."px;";
            }
            $styles[$name][$style_index] = $style;
        }
    }
    return $styles;
}
/**
 * Is the argument an HTML tag?
 * @param $tag is it a tag?
 */
$html_tags = array(
    'div' => true,
    'h1' => true,
    'h2' => true,
    'h3' => true,
    'input' => true,
    'p' => true,
    'select' => true
);
function get_tag($tag) {
    GLOBAL $html_tags;
    if (array_key_exists($tag, $html_tags)) {
        return $tag;
    } else {
        //Try no []s
        $tmp = preg_split("|\[|", $tag);
        if (count($tmp) > 1) {
            //if the first one is a tag :
            if (array_key_exists($tmp[0], $html_tags)) {
                //Then remove ']' from the second and return the join
                return $tmp[0]." ".str_replace("]", "", $tmp[1]);
            }
        }
    }
    return false;
}
/**
 * Return example content for the tag type (i.e. options for a select)
 * @param $tag HTML tag
 * @param $name Bumpf
 */
function get_content_for_tag($tag, $name) {
    switch($tag) {
    case 'select':
        return "<option>Please choose...</option><option>$name</option>";
    }
    return $name;
}
//Set the domain
$components = parse_url($url);
$domain = $components['scheme']."://";
if (array_key_exists('user', $components)) {
    $domain .= $components['user'];
    $components['host'] = "@".$components['host'];
}
if (array_key_exists('pass', $components)) {
    $domain .= ":".$components['pass'];
}
$domain .= $components['host'];
if (array_key_exists('port', $components)) {
    $domain .= ":".$components['port'];
}
define('domain', $domain);
define('path', dirname($components['path']));
//fetch the URL
$css = file_get_contents($url);
if (strlen($css) == 0) {
    die ("Nothing in $url");
}
check_imports($css, $url);
$styles = get_styles($css);
$break = <<<EOF
border: 1px dotted #000000;
EOF;
$in_comment = false;
$id = 1;
$divs = <<<EOF
        <div id="all_list" style="display:none;">
            <a href="javascript:viewGallery();" style="z-index:10;position:relative;">
                View gallery
            </a>
            <br/><br/>
EOF;
$style_list = <<<EOF
            <select name="style_select" onChange="javascript:view(this.options[this.selectedIndex].value);">
EOF;
foreach($styles as $name => $style) {
    if ($in_comment && !strstr($name, '*/')) {
        continue;
    }
    else if ($in_comment) {
        $in_comment = false;
        continue;
    }
    //if (!($mod = get_mod($name))) {
    if (substr($name,0,1) == '/') {
        //in_comment
        if (substr($name, 1, 1) == '/') {
            continue; //single-line
        }
        if (substr($name, 1, 1) == '*') {
            if (!strstr($name, '*/')) {
                $in_comment = true;
            } else {
                while (preg_match("|/\*.*\*/|ms", $name)) {
                    $name = trim(preg_replace("|/\*.*\*/|ms", "", $name));
                }
            }
        }
        continue;
    }
    $style_desc = "* ".join("<br/>* ", $style)."<br/>";
	$style_actual = join("",$style);
        //Find the tag(s) that are affected by this style
    $lumps = preg_split("|,? |", $name);
    $tags = array();
    foreach($lumps as $lump) {
        //If it's a tag, make it the tag
        if ($tag = get_tag($lump)) {
            $tags[] = $tag;
        }
        //If it's got a dot or an octothorpe, split on that and check those for tags
        $sub_lumps = preg_split("/[\.|#]/", $lump);
        if (count($sub_lumps) > 1) {
            foreach($sub_lumps as $sub_lump) {
                if ($tag = get_tag($sub_lump)) {
                    $tags[] = $tag;
                }
            }
        }
    }
    if (!count($tags)) {
        $tags = array("div");
    }
        $style_list .= <<<EOF
                <option value="class_{$id}_outer">{$name}</option>
EOF;
        $divs.= <<<EOF
            <div id="class_{$id}_outer" name="class_{$id}_outer">
                <div id="class_{$id}_info" name="class_{$id}_info">
                    <p>
                        $name :
                    </p>
                    {$style_desc}
				</div>
				<div id="class_{$id}_wrapper" name="class_{$id}_wrapper" style="{$break}">
EOF;
    foreach($tags as $tag) {
        //Tag could be an input with a type in, so make a closing tag of the first word only
        $close_tag = $tag;
        $closers = preg_split("| |", $tag);
        if (count($closers) > 1) {
            $close_tag = $closers[0];
        }
        $content = get_content_for_tag($tag, $name);
        $divs .= <<<EOF
                    <p>
                        {$tag} :
                    </p>
                    <{$tag} style="{$style_actual}">
                        {$content}
                    </{$close_tag}>
EOF;
	}
        $divs .= <<<EOF
                 </div>
            </div>
EOF;
    $id++;
}
$divs .= <<<EOF
        </div>
EOF;
$style_list .= <<<EOF
            </select>
EOF;
require("gallery.html");
delete_images();
/**
 * Add the images to the deletion queue
 */
function delete_images() {
    global $images;
    file_put_contents("delete_list", join("\n", $images)."\n", FILE_APPEND | LOCK_EX);
}
