<?php
//phpinfo();
//uncomment for on-screen debugging
//ini_set('display_errors', 1); 
//#error_reporting(E_ALL);
/************************************************************************************************
 *
 *  YarrrRSS version 1.0.0 RC8
 *  By Zach Armstrong
 *
 *  License:  Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported
 *  License information: http://creativecommons.org/licenses/by-nc-sa/3.0/
 *
 *  Please view the license text before making changes to the code.
 *  Remember:  Sharing is caring!
 *
 *  file:  tpb-rss.php
 *
 *  Basic Instructions:
 *  Please customize the SECRET_KEY below.
 *
 *  Hit this page passing two GET variables, key and:
 *
 *    category 
 *      or
 *    user
 *      or
 *    search [plus categories optional] 
 *
 *  where key is the SECRET_KEY specified below and:
 *
 *    category is the category # from The Pirate Bay
 *
 *    or user is the user name from The Pirate Bay
 *
 *    or search is the search term and searchCats is an optional list
 *      of comma separated categories from The Pirate Bay 
 *
 *  Example: 
 *  For the Audio > Music category, I would use  tpb-rss.php?key=MySecretCode&category=101
 *  For TvTeam uploads, I would use tpb-rss.php?key=MySecretCode&user=TvTeam
 *  For searching for Dexter, I would use tpb-rss.php?key=MySecretCode&search=Dexter
 *
 *  CACHE INFO:
 *  
 *  The cache feature is to lessen load both on your server, but more importantly
 *  The Pirate Bay. Some clients may update RSS very frequently, and this feature is 
 *  designed to lighten that load.
 *
 *  In order for the cache to work, the CACHE_PATH must exist and the apache process
 *  must have read/write permission to it.
 *
 *  CACHE_TIME is set in minutes, and should be higher than 60 minutes to be useful. The 
 *  default is 120 minutes.
 *  
 *  The cache setting can be overridden via the GET variable bypass_cache
 *  Set it to 1 in order to bypass the cache.
 *
 *  Example: 
 *    tpb-rss.php?key=MySecretCode&category=101&bypass_cache=1
 *  
 *  OPTIONAL PARAMETERS / EXTENDED FEATURES:
 *  
 *    TRUST FILTERS
 *    
 *    You can filter results based on VIP and/or TRUSTED status. To do so, add the following
 *    parameter to the URI:
 *  
 *    Filter based on VIP only: 		TrustedVIP=1
 *    Filter based on TRUSTED only: 		TrustedVIP=2 
 *    Filter based on TRUSTED or VIP only: 	TrustedVIP=3
 *  	
 *    EXTENDED RESULTS
 *    
 *    Extended results combines multiple pages of results into one RSS feed. This is AWESOME
 *    because the odds of missing a torrent is greatly lessened. 
 *    This feature is great when used in combination with the cache feature, as it puts a lot less
 *    load on your server as well as thepiratebay.
 *    If you use this feature and use caching, I recommend increasing the cache time by 20-30 minutes 
 *    per page.
 *    
 *    To use this feature, add the following parameter to the URI:
 *    
 *    pages=# 
 *    
 *    where # is the number of pages to request (maximum of 10)
 *    
 *    The 10 page limit is hardcoded, but can be changed if you desire. I don't recommend increasing this value
 *    as you will unlikely gain much from anything higher. Also, in my testing, 10 pages of results takes about 
 *    15 seconds to render. Increasing this higher will begin to create timeouts. Diminishing returns, people. 
 *    
 *    
 *
 ************************************************************************************************/

define('SECRET_KEY', "tpbrulez");
define('PERF_DATA', false);

if (PERF_DATA) $starttime = getTime();

define('ENABLE_CACHE', true);
define('CACHE_PATH', "cache/");
define('CACHE_TIME', '120');

define('SHOW_TRUSTS', true);
define('SHOW_VIPS', true);

//please dont change below this line
define('YarrrRSS_VERSION', "1.0.0 RC8");
define('MAXPAGES', 10);

if (!check_secret_key())
	die("Wrong key or no key specified");

isset($_GET["pages"]) ? $_GET["pages"] > MAXPAGES ? $pageCount=MAXPAGES : $pageCount=$_GET["pages"] : $pageCount=1;
if (isset($_GET["category"]))
{
	for ($pCount = 0; $pCount < $pageCount; $pCount++)
	{
		$siteURI[$pCount] = "http://thepiratebay.se/browse/" . $_GET["category"] . "/$pCount/3";
	}
	$cacheFilename = CACHE_PATH . "cat_".md5($_GET["category"]) . "_p" . $pageCount;
}
elseif (isset($_GET["usr"]) || isset($_GET["user"]))
{
	for ($pCount = 0; $pCount < $pageCount; $pCount++)
	{
		$userName = isset($_GET["usr"]) ? $_GET["usr"] : $_GET["user"];
		$siteURI[$pCount] = "https://thepiratebay.se/user/" . encodeurl($userName)."/$pCount/3";
	}
	$cacheFilename = CACHE_PATH. "user_" . md5($userName) . "_p".$pageCount;
}	
elseif (isset($_GET["search"]))
{
	for ($pCount = 0; $pCount < $pageCount; $pCount++)
	{
		$siteURI[$pCount] = "http://thepiratebay.se/search/" . encodeurl($_GET["search"]) . "/$pCount/7/" . (isset($_GET["searchCats"]) ? $_GET["searchCats"] : "0");
	}
	$cacheFilename = CACHE_PATH . "search_" . md5(isset($_GET["searchCats"]) ? $_GET["search"].$_GET["searchCats"] : $_GET["search"]) . "_p" . $pageCount;
}
else 
{
	die("missing parameter");
}

isset($_GET["TrustedVIP"]) ? $trustSetting=$_GET["TrustedVIP"] : $trustSetting=0;
isset($_GET["bypass_cache"]) ? $bypass_cache=$_GET["bypass_cache"] : $bypass_cache=0; 

if ((ENABLE_CACHE) && ($bypass_cache==0))
{
	getCache($cacheFilename);
}

//grab all data quickly - process after -- this should prevent duplicates 
//due to slow responses most of the time
if (PERF_DATA) $tpbtime=getTime();
for ($pCount = 0; $pCount < $pageCount; $pCount++)
{
	$data[$pCount] = getTPB($siteURI[$pCount]);
}
if (PERF_DATA) $tpbtime = getTime() - $tpbtime;

if (PERF_DATA) $looptime = getTime();

for ($pCount = 0; $pCount < $pageCount; $pCount++)
{

	if ($data[$pCount])
	{
		$patternTorrentTable = "/(?i)(?:<table id=\"searchResult\">)(?<torrentTable>[\d\W\w\s ,.]*?)(?:<\/table>)/";  //[\d\W\w\s ,.]
		preg_match_all($patternTorrentTable, $data[$pCount], $torrentTableDataArr);

		$torrentTableData = $torrentTableDataArr['torrentTable'][0];
		
		if ($pCount>0)
		{
			$patternHits = "/(?i)(Displaying hits from 1 to )/";
			preg_match_all($patternHits, $data[$pCount], $torrentHits);
			if (sizeof($torrentHits[0]) > 0) continue;
		}

		if (isset($_GET["category"]))
		{
			$patternCat = "/(?i)(<h2><span>Browse )(?<categoryName>[\d\W\w\s &;,.]*?)(<\/span>)/";
			preg_match_all($patternCat, $data[$pCount], $torrentCategoryName);

			$feedCat = $torrentCategoryName['categoryName'][0];
			$feedDesc = "filed under: $feedCat";
			$feedTitle = "Category: $feedCat";
		}	
		elseif (isset($userName))
		{
			$feedDesc = "uploaded by: $userName";
			$feedTitle = "Uploads by $userName";
		}
		elseif (isset($_GET["search"]))
		{
			$feedDesc = "search results for: ".$_GET["search"];
			$feedTitle = "Search Results for: ".$_GET["search"];
		}

		$patternSingleTorrent = "/(?i)(<tr>)(?<singleTorrent>[\d\W\w\s ,.]*?)(?:<\/tr>)/";
		preg_match_all($patternSingleTorrent, $torrentTableData, $torrentSingleData);

		$rssBody = "";
		$loops=count($torrentSingleData['singleTorrent'])-1;

		for ($row = 0; $row < $loops ; $row++)
		{
			//make sure we're not in the pagination
			$patternPagination = "/(?i)td colspan=\"9\"/";
			if (preg_match_all($patternPagination, $torrentSingleData['singleTorrent'][$row], $pagination)) continue;		

			//parse categories
			$patternCategory = "/(?i)More from this category\">(?<torrentCategories>[\d\W\w\s ,.-]*?)<\/a>/";
			preg_match_all($patternCategory, $torrentSingleData['singleTorrent'][$row], $torrentCats);
			$torrentCategories = $torrentCats['torrentCategories'][0] ." -&gt; " . $torrentCats['torrentCategories'][1];

			//parse torrent URL
			$patternInfo = "/(?i)(a href=\"\/torrent\/)(?<torrentDescURL>[\d\W\w\s ,.]*?)(\" class=\"detLink\" title=\"Details for )(?<torrentTitle>[\d\W\w\s ,.]*?)(\")/";
			preg_match_all($patternInfo, $torrentSingleData['singleTorrent'][$row], $torrentInfo);

			//parse torrent upload time and size
			$patternUploadTimeAndSize = "/(?i)Uploaded (?<torrentTime>[\d\W\w\s -:]*?), Size (?<torrentSize>[\d\W\w\s .]*?),/";
			preg_match($patternUploadTimeAndSize, $torrentSingleData['singleTorrent'][$row], $UploadTimeAndSize);

			$torrentUploadTime = strip_tags($UploadTimeAndSize['torrentTime']);
			
			$patterns = '/mins/';
			$replacements = 'minutes';
			$torrentUploadTime = preg_replace($patterns, $replacements, $torrentUploadTime);
			$patterns = '/&nbsp;/';
			$replacements = ' ';			
			$torrentUploadTime = preg_replace($patterns, $replacements, $torrentUploadTime);
			$patterns = '/Y-day/';
			$replacements = 'yesterday';
			$torrentUploadTime = preg_replace($patterns, $replacements, $torrentUploadTime);
			$patterns = '/(\d{2})-(\d{2}) (\d{2}):(\d{2})/';
			$replacements = '\3:\4 '.date("Y").'-\1-\2';				
			$torrentUploadTime = preg_replace($patterns, $replacements, $torrentUploadTime);
			$patterns = '/(\d{2})-(\d{2}) (\d{4})/';
			$replacements = '\3-\1-\2';				
			$torrentUploadTime = gmdate('r', strtotime(preg_replace($patterns, $replacements, $torrentUploadTime)));

			
			$torrentUploadSize = sizeToBytes($UploadTimeAndSize['torrentSize']);

			//parse trusted
			//$patternTrusted = "/(?i)(<img src=\"http:\/\/static.thepiratebay.se\/img\/trusted.png)/";
			$patternTrusted = "/(?i)(<img src=\"\/static\/img\/trusted.png)/";

			preg_match_all($patternTrusted, $torrentSingleData['singleTorrent'][$row], $torrentTrusted);

			//parse VIP
			$patternVIP = "/(?i)(<img src=\"\/static\/img\/vip.gif)/";
			preg_match_all($patternVIP, $torrentSingleData['singleTorrent'][$row], $torrentVIP);

			//parse torrent title
			$torrentTitle = $torrentInfo['torrentTitle'][0];

			$torrentDescUrl = "http://" . encodeurl("thepiratebay.se/torrent/" . $torrentInfo['torrentDescURL'][0]);

			//parse torrent magnet URI
			$patternInfo = "/(?i)(<a href=\"magnet:\?)(?<torrentMagnetURI>[\d\W\w\s ,.]*?)(\" title=\"Download this torrent using magnet)/";
			preg_match_all($patternInfo, $torrentSingleData['singleTorrent'][$row], $torrentInfo);

			$torrentMagnetURI = "?" . $torrentInfo['torrentMagnetURI'][0];
			$torrentMagnetEncodedURI = str_replace("&", "&amp;", $torrentMagnetURI);
			
			$patternInfo = "/(?i)(btih:)(?<torrentMagnetHash>[\d\W\w\s ,.]*?)(&dn)/";
			preg_match_all($patternInfo, $torrentMagnetURI, $torrentMagnet);

			$torrentMagnetHash = $torrentMagnet['torrentMagnetHash'][0];			

			switch($trustSetting)
			{
				default:
				case 0:
					if (SHOW_VIPS && sizeof($torrentVIP[0]) > 0) $torrentTitle = "[VIP] " . $torrentTitle;
					if (SHOW_TRUSTS && sizeof($torrentTrusted[0]) > 0) $torrentTitle = "[Trusted] " . $torrentTitle;			
					$rssBody .=  "   <item>\n"
						   ."      <title><![CDATA[$torrentTitle]]></title>\n"
						   ."      <pubDate>$torrentUploadTime</pubDate>\n"
						   ."      <guid isPermaLink=\"true\">$torrentDescUrl</guid>\n"
						   ."      <link>magnet:$torrentMagnetEncodedURI</link>\n"
						   ."      <category>$torrentCategories</category>\n"
						   ."      <torrent xmlns=\"http://xmlns.ezrss.it/0.1/\">\n"
						   ."      	<contentLength>$torrentUploadSize</contentLength>\n"
						   ."      	<infoHash>$torrentMagnetHash</infoHash>\n"
						   ."      	<magnetURI><![CDATA[magnet:$torrentMagnetURI]]></magnetURI>\n"
						   ."      </torrent>\n"
						   ."      <description><![CDATA[Magnet: <a href=\"$torrentMagnetURI\">$torrentTitle</a>]]>\n"
						   ."      </description>\n"
						   ."   </item>\n";
				break;
				case 1:
					if (sizeof($torrentVIP[0]) > 0)		
					{
						if (SHOW_VIPS) $torrentTitle = "[VIP] " . $torrentTitle;
						$rssBody .=  "   <item>\n"
							   ."      <title><![CDATA[$torrentTitle]]></title>\n"
							   ."      <pubDate>$torrentUploadTime</pubDate>\n"
							   ."      <guid isPermaLink=\"true\">$torrentDescUrl</guid>\n"
							   ."      <link>magnet:$torrentMagnetEncodedURI</link>\n"
							   ."      <category>$torrentCategories</category>\n"
							   ."      <torrent xmlns=\"http://xmlns.ezrss.it/0.1/\">\n"
							   ."      	<contentLength>$torrentUploadSize</contentLength>\n"
							   ."      	<infoHash>$torrentMagnetHash</infoHash>\n"
							   ."      	<magnetURI><![CDATA[magnet:$torrentMagnetURI]]></magnetURI>\n"
							   ."      </torrent>\n"
							   ."      <description><![CDATA[Magnet: <a href=\"$torrentMagnetURI\">$torrentTitle</a>]]>\n"
							   ."      </description>\n"
							   ."   </item>\n";
					}
				break;
				case 2:
					if (sizeof($torrentTrusted[0]) > 0)		
					{
						if (SHOW_TRUSTS) $torrentTitle = "[Trusted] " . $torrentTitle;
						$rssBody .=  "   <item>\n"
							   ."      <title><![CDATA[$torrentTitle]]></title>\n"
							   ."      <pubDate>$torrentUploadTime</pubDate>\n"
							   ."      <guid isPermaLink=\"true\">$torrentDescUrl</guid>\n"
							   ."      <link>magnet:$torrentMagnetEncodedURI</link>\n"
							   ."      <category>$torrentCategories</category>\n"
							   ."      <torrent xmlns=\"http://xmlns.ezrss.it/0.1/\">\n"
							   ."      	<contentLength>$torrentUploadSize</contentLength>\n"
							   ."      	<infoHash>$torrentMagnetHash</infoHash>\n"
							   ."      	<magnetURI><![CDATA[magnet:$torrentMagnetURI]]></magnetURI>\n"
							   ."      </torrent>\n"
							   ."      <description><![CDATA[Magnet: <a href=\"$torrentMagnetURI\">$torrentTitle</a>]]>\n"
							   ."      </description>\n"
							   ."   </item>\n";
					}
				break;
				case 3:
					if ( (sizeof($torrentTrusted[0]) > 0) || (sizeof($torrentVIP[0]) > 0)	)
					{
						if (SHOW_VIPS && sizeof($torrentVIP[0]) > 0) $torrentTitle = "[VIP] " . $torrentTitle;
						if (SHOW_TRUSTS && sizeof($torrentTrusted[0]) > 0) $torrentTitle = "[Trusted] " . $torrentTitle;
						$rssBody .=  "   <item>\n"
							   ."      <title><![CDATA[$torrentTitle]]></title>\n"
							   ."      <pubDate>$torrentUploadTime</pubDate>\n"
							   ."      <guid isPermaLink=\"true\">$torrentDescUrl</guid>\n"
							   ."      <link>magnet:$torrentMagnetEncodedURI</link>\n"
							   ."      <category>$torrentCategories</category>\n"
							   ."      <torrent xmlns=\"http://xmlns.ezrss.it/0.1/\">\n"
							   ."      	<contentLength>$torrentUploadSize</contentLength>\n"
							   ."      	<infoHash>$torrentMagnetHash</infoHash>\n"
							   ."      	<magnetURI><![CDATA[magnet:$torrentMagnetURI]]></magnetURI>\n"
							   ."      </torrent>\n"
							   ."      <description><![CDATA[Magnet: <a href=\"$torrentMagnetURI\">$torrentTitle</a>]]>\n"
							   ."      </description>\n"
							   ."   </item>\n";
					}						
			}
		}


		if ($pCount==0)
		{
			header('Content-type: text/xml'); 
			echo createRSSHead($feedTitle,$feedDesc);
			$fullRSS = createRSSHead($feedTitle,$feedDesc);
		}

		echo $rssBody;
		$fullRSS .= $rssBody;

		if ($pCount == $pageCount-1)
		{
			$fullRSS .= createRSSFoot();
			echo createRSSFoot();
		}

		if (ENABLE_CACHE && ($bypass_cache == 0) && ($pCount == $pageCount-1))
		{
			$handle = fopen($cacheFilename,"wb");
			fwrite($handle, $fullRSS);
			fclose($handle);
		}
	} 
	else //file_get_contents errored out
	{
		if (ENABLE_CACHE)
		{
			getCache($cacheFilename);
		}
		else
		{
			$fullRSS = createRSSHead("No data received","feed returned no data") . createRSSFoot();
			if ($pCount == 0)  header('Content-type: text/xml'); 
			echo $fullRSS;	
		}
	}
}
exit;

/**********************
 *   Functions below  *
 **********************/
 
function check_secret_key()
{
	$providedkey=$_GET['key'];
	if (SECRET_KEY != $providedkey)
		return false;
	return true;
}

function createRSSHead($feedTitle,$feedDesc)
{

	$rssHead = "<?xml version='1.0' encoding='UTF-8' ?>\n"
		   ."<!DOCTYPE torrent PUBLIC \"-//bitTorrent//DTD torrent 0.1//EN\" \"http://xmlns.ezrss.it/0.1/dtd/\">\n"
		   ."<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n"
		   ."   <channel> \n"
		   ."   <title>The Pirate Bay - $feedTitle</title> \n"
		   ."   <link>" . curPageURL() . "</link> \n"
		   ."   <description>Latest TPB torrents $feedDesc</description> \n"
		   ."   <lastBuildDate>" . gmdate('r') . "</lastBuildDate> \n"
		   ."   <generator>YarrrRSS v" . YarrrRSS_VERSION . "</generator> \n"
		   ."   <language>en-us</language> \n";
	if (ENABLE_CACHE)
		$rssHead .= "   <ttl>" . CACHE_TIME . "</ttl> \n";

	return $rssHead;
}

function createRSSFoot()
{
	$rssFoot = "   </channel> \n"
    		  ."</rss> ";
	if (PERF_DATA) 
	{
		global $tpbtime, $looptime,  $starttime;
		$looptimetotal = getTime() - $looptime;
		$endtime = getTime()- $starttime;
		$rssFoot = "<perfdata>"
		     	  ."<![CDATA[\n"
		     	  ."TPB time: $tpbtime\n"
		     	  ."Loop time: $looptimetotal\n"
		     	  ."Other time: " . ($endtime - ($tpbtime + $looptimetotal)) . "\n"
		     	  ."Total render time: $endtime" 
		    	   ."\n      ]]>\n"
		    	   ."</perfdata>\n"
		    	   . $rssFoot;
	}
    	return $rssFoot;
}

function sizeToBytes($sizeString)
{
 	$vals = array(  'TiB' => 1000*1000*1000*1000,
 			'TB' => 1024*1024*1024*1024,
 			'GiB' => 1000*1000*1000,
 			'GB' => 1024*1024*1024,
 			'MiB' => 1000*1000,
 			'MB' => 1024*1024,
 			'KiB' => 1000,
 			'KB' => 1024  
 		);
   
	$keys = array_keys($vals);
	for( $i = 0; $i < count($keys); $i++)
	{
		if(strpos($sizeString, $keys[$i]) !== false)
	    		$fileSize = intval($sizeString) * $vals[$keys[$i]];
	}   

	return $fileSize;
}

function curPageURL()
{
	$pageURL = 'http';
	if (!empty($_SERVER['HTTPS']))
		if ($_SERVER["HTTPS"] == "on")
			$pageURL .= "s";
 	$pageURL .= "://";
 	if ($_SERVER["SERVER_PORT"] != "80") 
 	{
  		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].encodeurl($_SERVER["REQUEST_URI"]);
 	}
 	else 
 	{
  		$pageURL .= $_SERVER["SERVER_NAME"].encodeurl($_SERVER["REQUEST_URI"]);
 	}
	return $pageURL;
}

function getCache($cacheFilename)
{
	if (file_exists($cacheFilename) && ( filemtime($cacheFilename) + (CACHE_TIME*60) > (time())))
	{
		header('Content-type: text/xml'); 
    		readfile($cacheFilename);
    		exit;
	}
}

function encodeurl($toEncode)
{
	$patterns = array();
	$patterns[0] = '/%2F/';
	$replacements = array();
	$replacements[0] = '/';
	$toEncode = urlencode($toEncode);
	$toEncode = preg_replace($patterns, $replacements, $toEncode);
	return $toEncode;	
}

function getTPB($siteURI)
{
//        echo "$siteURI";

	$curlSession = curl_init();
	curl_setopt($curlSession, CURLOPT_URL, $siteURI);
	curl_setopt($curlSession, CURLOPT_HEADER, 0);
	curl_setopt($curlSession, CURLOPT_TIMEOUT, 13);
	curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($curlSession, CURLOPT_USERAGENT, "Mozilla/4.1 (compatible; MSIE 5.01; Windows NT 5.0)");
	$data = curl_exec($curlSession);
	curl_close($curlSession);
	return $data;
}

function getTime() { 
    $timer = explode( ' ', microtime() ); 
    $timer = $timer[1] + $timer[0]; 
    return $timer; 
}
?>
