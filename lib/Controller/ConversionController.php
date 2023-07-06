<?php
namespace OCA\ytdl\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use \OCP\IConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OC\Files\Filesystem;


class ConversionController extends Controller {

	private $userId;

	/**
	* @NoAdminRequired
	*/
	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;

	}

	public function getFile($directory, $fileName){
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($this->userId);
		return Filesystem::getLocalFile($directory . '/' . $fileName);
	}
	/**
	* @NoAdminRequired
	*/
	public function convertHere($nameOfFile, $directory, $external, $type, $playlist = false, $url = null, $shareOwner = null, $mtime = 0) {
		$response = array();
		$dir = $this->getFile($directory, $nameOfFile);
		if (!is_dir($dir)) {
			$dir = dirname($this->getFile($directory, $nameOfFile));
		}
		/*else {
			$dir = $directory.$nameOfFile;
		}*/
		$cmd = $this->createCmd($dir, $type, $url, $playlist);
		$output = "";
		exec($cmd, $output,$return);
		$str = implode(" ",$output);
		if (sizeof($output) == 0) {
			$response = array_merge($response, array('error' => 'url : '.$url.' not downloadable or not found, cmd : '.$cmd));
		}else {
			$filePath = trim(explode("Adding metadata to", $str)[1]);
			$filePath = substr($filePath,1,-1);
			$filepath2 = strstr($filePath, $dir);
			$response = array_merge($response, array("code" => 1, "cmd" => $cmd));
			exec("php /var/www/nextcloud/occ files:scan ".$this->userId, $output, $return);
		}
		return json_encode($response);
	}
	/**
	* @NoAdminRequired
	*/
	public function createCmd($dir, $typeParam, $url, $playlistParam){
		$middleArgs = "";
		$type = strtolower($typeParam);
		switch($type) {
			case "mp3":case "mp4": case "flac": $middleArgs = "bestaudio -x --audio-format ".$type;break;
			case     "native_audio":   $middleArgs = "bestaudio";break;
			default:                   $middleArgs = "bestvideo[height\<=?1080]+bestaudio";break;
		}
		$middleArgs.=" --buffer-size 16k";
		if ( $playlistParam !== "false" && str_contains(parse_url($url, PHP_URL_QUERY), 'list=') ) {
			$dir.="/%(playlist)s/%(playlist_index)s - ";
			$middleArgs.=" --add-metadata --yes-playlist -i \"".$url."\"";
		}else {
			$dir.="/";
			$middleArgs.=" --add-metadata ".$url;
		}
		return  "yt-dlp -o '".$dir."%(title)s.%(ext)s' -f ".$middleArgs;
	}
}
