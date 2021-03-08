<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Scrapping
{
	protected $ci;

	public function __construct()
	{
        $this->ci =& get_instance();
	}

	public function get_data_string($html, $query)
	{
		$crawl_doc = new DOMDocument();
		$data      = array();
		libxml_use_internal_errors(TRUE);

		if (!empty($html))
		{
			$crawl_doc->loadHTML($html);
			libxml_clear_errors();

			$krk_xpath = new DOMXPath($crawl_doc);
			$krk_row   = $krk_xpath->query($query);

			if ($krk_row->length > 0)
			{
				foreach ($krk_row as $row)
					$data[] = preg_replace('/\s+/', ' ', $row->nodeValue);
			}
		}

		return $data;
	}

	public function get_data($url, $xpath_query)
	{
		$context_options['ssl']['verify_peer'] = false;
		$context_options['ssl']['verify_peer_name'] = false;

		$headers = get_headers($url, false, stream_context_create($context_options));
		$status  = substr($headers[0], 9, 3);

		if ($status == '200')
		{
			$html      = file_get_contents($url, false, stream_context_create($context_options));
			$crawl_doc = new DOMDocument();
			$data      = array();
			libxml_use_internal_errors(TRUE);

			if (!empty($html))
			{
				$crawl_doc->loadHTML($html);
				libxml_clear_errors();

				$krk_xpath = new DOMXPath($crawl_doc);
				$krk_row   = $krk_xpath->query($xpath_query);

				if ($krk_row->length > 0)
				{
					foreach ($krk_row as $row)
						$data[] = preg_replace('/\s+/', ' ', $row->nodeValue);
				}
			}
			else
			{
				$data['status']  = 'error';
				$data['message'] = 'Content Empty';
			}
		}
		else
		{
			$data['status']  = 'error';
			$data['message'] = 'Site Not Accessible';
		}

		return $data;
	}

	public function get_data_curl($url, $header = array(), $param = array(), $method = 'POST', $port = null)
	{
		$ch = curl_init();

		if (is_array($param) && !empty($param))
			$param = http_build_query($param);

		curl_setopt($ch, CURLOPT_PORT, $port);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, 1);

		$server_output = curl_exec ($ch);

		echo curl_error($ch);

		curl_close ($ch);

		return $server_output;
	}

	public function get_data_json($url, $export_json = FALSE)
	{
		$context_options['ssl']['verify_peer'] = false;
		$context_options['ssl']['verify_peer_name'] = false;
		
		$data = file_get_contents($url, false, stream_context_create($context_options));

		if ($export_json)
		{
			$data = json_decode($data, TRUE);
			return json_encode($data);
		}
		else
		{
			return json_decode($data, TRUE);
		}
	}

	public function url_check($url, $header, $conn_timeout = 2, $timeout = 2, $return_transfer = -1)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, $return_transfer);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $conn_timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);

		return $http_code;
	}

	public function export_csv($data, $filename, $header)
	{
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename = '.$filename.'.csv');

		$fp = fopen('php://output', 'w');
		fputcsv($fp, $header);
		
		foreach($data as $row)
			fputcsv($fp, $row);

		fclose($fp);
	}
}

/* End of file Scrapping.php */
/* Location: ./application/libraries/Scrapping.php */