<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Opentender_lib
{
	protected $ci;

	public function __construct()
	{
        $this->ci =& get_instance();
        $this->ci->load->library('scrapping');
	}

	public function url_check($url)
	{
		$context_options['ssl']['verify_peer'] = false;
		$context_options['ssl']['verify_peer_name'] = false;

		$headers = get_headers($url, false, stream_context_create($context_options));
		$status  = substr($headers[0], 9, 3);

		return $status;
	}

	public function get_tender_data($url, $header, $query, $format = 'json')
	{
		$raw_data        = $this->ci->scrapping->get_data_curl($url, $header);
		$structured_data = $this->ci->scrapping->get_data_string($raw_data, $query);

		foreach ($structured_data as $key => $value)
			$structured_data[$key] = trim($value);

		if ($format == 'json')
			return json_encode($structured_data);
		else
			return $structured_data;
	}

	public function cleaning_format($data, $format = 'empty')
	{
		$data = trim($data);

		if ($format == 'tanggal')
		{
			$bulan_idn['Januari']   = '01';
			$bulan_idn['Februari']  = '02';
			$bulan_idn['Maret']     = '03';
			$bulan_idn['April']     = '04';
			$bulan_idn['Mei']       = '05';
			$bulan_idn['Juni']      = '06';
			$bulan_idn['Juli']      = '07';
			$bulan_idn['Agustus']   = '08';
			$bulan_idn['September'] = '09';
			$bulan_idn['Oktober']   = '10';
			$bulan_idn['November']  = '11';
			$bulan_idn['Desember']  = '12';

			$data = explode(' ', $data);
			$result = $data[2].'-'.$bulan_idn[$data[1]].'-'.$data[0];
		}

		if ($format == 'uang')
		{
			if (!empty($data) OR !isset($data))
			{
				$temp = $data;
				$data = substr($data, 3, -3);
				$result = str_replace('.', '', $data);

				if (!is_numeric($result))
					$result = $temp;
			}
			else
			{
				$result = 'N/A';
			}
		}

		if ($format == 'peserta')
			$result = str_replace(' peserta', '', $data);

		if ($format == 'empty')
		{
			if ((!isset($data)) OR empty($data))
				$result = 'N/A';
			else
				$result = $data;
		}

		return $result;
	}

	public function reformat_dictionary($dictionary)
	{
		$ta_weight = file_get_contents($dictionary);
		$ta_weight = json_decode($ta_weight, TRUE);

		//Reformating Text Weighting Array
		$terms = array();
		foreach ($ta_weight as $keys => $values)
		{
			$terms[$keys]['terms'] = array();
			$terms[$keys]['weight'] = array();
			foreach ($values as $key => $value)
			{
				array_push($terms[$keys]['terms'], $value['terms']);
				array_push($terms[$keys]['weight'], $value['weight']);
			}
		}

		return $terms;
	}
}

/* End of file Opentender_lib.php */
/* Location: ./application/libraries/Opentender_lib.php */