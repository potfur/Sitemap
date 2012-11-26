<?php
namespace sitemap;

/**
 * XML Builder
 * Builds XML sitemap from passed data and writes it into set file
 *
 * @package Moss Sitemap
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
class Builder {
	protected $outputFile;

	/**
	 * Constructor
	 *
	 * @param string $output
	 */
	public function __construct($output) {
		$this->outputFile = $output;
	}

	/**
	 * Sets output XML filename
	 *
	 * @param $filename
	 *
	 * @return Builder
	 */
	public function setOutput($filename) {
		if(!empty($filename)) {
			$this->outputFile = $filename;
		}

		return $this;
	}

	/**
	 * Retrieves output XML filename
	 *
	 * @return string
	 */
	public function getOutput() {
		return $this->outputFile;
	}

	/**
	 * Builds XML sitemap from passed data
	 *
	 * @param array $data
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function build($data = array()) {
		if(empty($data)) {
			throw new \InvalidArgumentException('No data');
		}

		if(!$this->xml($data)) {
			throw new \InvalidArgumentException('Error occoured while generating XML');
		}

		return $this->outputFile;
	}

	/**
	 * Writes generated into XML file
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	protected function xml($data = array()) {
		$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
		foreach($data as $url => $uArr) {
			$node = $xml->addChild('url');
			$node->addChild('loc', htmlspecialchars($url));
			$node->addChild('changefreq', $uArr['changeFreq']);
			$node->addChild('priority', $uArr['priority']);
		}

		$dom = new \DOMDocument('1.0');
		$dom->preserveWhiteSpace = false;
		$dom->loadXML($xml->asXML());
		$dom->formatOutput = true;
		return file_put_contents($this->outputFile, $dom->saveXML()) !== false;
	}
}