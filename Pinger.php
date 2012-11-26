<?php
namespace sitemap;

/**
 * Pinger
 * Pings set urls notifying them of new sitemap
 *
 * @package Moss Sitemap
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
class Pinger {
	protected $path;
	protected $placeholder;
	protected $urls;

	/**
	 * @param string $path
	 * @param string $placeholder
	 */
	public function __construct($path, $placeholder = '{path}') {
		$this->path = $path;
		$this->placeholder = $placeholder;
	}

	/**
	 * Sets urls to ping
	 *
	 * @param array $urls
	 *
	 * @return Pinger
	 */
	public function setUrls($urls = array()) {
		$this->urls = $urls;
		return $this;
	}

	/**
	 * Returns all set urls
	 *
	 * @return mixed
	 */
	public function getUrls() {
		return $this->urls;

	}

	/**
	 * Pings all set urls
	 *
	 */
	public function ping() {
		foreach($this->urls as $url) {
			$url = str_replace($this->placeholder, $this->path, $url);
			$ping = $this->sendPing($url);

			$this->message($ping, $url);
			flush();
		}
	}

	/**
	 * Executes ping to set url
	 *
	 * @param $url
	 *
	 * @return mixed
	 */
	protected function sendPing($url) {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_NOBODY, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
//		curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');

		$data = curl_exec($curl);
		curl_close($curl);

		return $data;
	}

	/**
	 * Sends message to stdOutput
	 *
	 * @param $message
	 * @param $url
	 */
	protected function message($message, $url) {
		echo sprintf("%s\t%s\n", $message, $url);
		flush();
	}
}
