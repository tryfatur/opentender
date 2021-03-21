<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Opentender extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->db->db_select('db_opentender');
		$this->load->library('scrapping');
		$this->load->library('opentender_lib');
		$this->load->library('useragent');

		date_default_timezone_set('Asia/Jakarta');

		set_time_limit(360000);
	}

	public function index()
	{
		/*$this->db->select('tier, COUNT(tier) jumlah');
		$this->db->group_by('tier');
		$datasource = $this->db->get('tbl_datasource_opentender')->result();*/

		$this->db->select('tier, COUNT(tier) jumlah');
		$this->db->where('http_code', 200);
		$this->db->group_by('tier');
		$rescrap_lpse = $this->db->get('tbl_rescrap_lpse')->result();

		$this->db->select('tier, COUNT(tier) jumlah');
		$this->db->where('api_opentender IS NOT null');
		$this->db->group_by('tier');
		$rescrap_opentender = $this->db->get('tbl_rescrap_opentender')->result();

		for ($i=0; $i < 89; $i++)
		{
			if ($i < 88)
				$target = 5000;
			else
				$target = 198;

			if (isset($rescrap_lpse[$i]))
				$lpse_scraped = $rescrap_lpse[$i]->jumlah;
			else
				$lpse_scraped = 0;

			if (isset($rescrap_opentender[$i]))
				$opentender_scraped = $rescrap_opentender[$i]->jumlah;
			else
				$opentender_scraped = 0;

			$stat[$i]['tier']               = $i;
			$stat[$i]['lpse_scraped']       = ($lpse_scraped / $target) * 100;
			$stat[$i]['lpse_left']          = (($target - $lpse_scraped) / $target) * 100;
			$stat[$i]['opentender_scraped'] = ($opentender_scraped / $target) * 100;
			$stat[$i]['opentender_left']    = (($target - $opentender_scraped) / $target) * 100;
		}

		$data['stat'] = $stat;

		$this->load->view('v_opentender/index', $data);
	}

	public function domain_check()
	{
		$this->db->where('domain_lpse_new IS NOT NULL');
		$this->db->where('http_code', 0);
		$data = $this->db->get('tbl_domain_check')->result();
		// $header = array('User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36');

		foreach ($data as $key => $value)
		{
			$status = $this->opentender_lib->url_check($value->domain_lpse_new);

			$this->db->where('domain_lpse_new', $value->domain_lpse_new);
			$this->db->update('tbl_domain_check', array('http_code' => $status));

			sleep(0.2);
		}

		echo "Done! (".$this->db->affected_rows().")";
	}

	public function scrap_data()
	{
		error_reporting(E_ERROR | E_PARSE);

		$tender = array();
		$tier = $this->input->get('tier');
		$time_processing = 0;

		$this->db->select('kode_lelang, domain_lpse_new, tier');
		$this->db->where('tier', $tier);
		$query_data = $this->db->get('tbl_datasource_opentender')->result();

		/*$this->db->select('kode_tender, link_pengumuman, link_pemenang, tier');
		$this->db->where('http_code', 302);
		$query_data = $this->db->get('tbl_rescrap_lpse')->result();*/

		/*$this->db->select('a.kode_lelang, a.domain_lpse_new, a.tier');
		$this->db->join('tbl_rescrap b', 'a.kode_lelang = b.kode_tender', 'left');
		$this->db->where('a.tier', 2);
		$this->db->where('b.nama_tender IS NULL');
		$query_data = $this->db->get('tbl_ot_refine_rd a')->result();*/

		foreach ($query_data as $key => $value)
		{
			$scrap_url[$key]['pengumumanlelang'] = $value->domain_lpse_new.'/lelang/'.$value->kode_lelang.'/pengumumanlelang';
			$scrap_url[$key]['pemenang']         = $value->domain_lpse_new.'/evaluasi/'.$value->kode_lelang.'/pemenang';
			$scrap_url[$key]['kode_lelang']      = $value->kode_lelang;
			$scrap_url[$key]['tier']             = $value->tier;

			// Update
			/*$scrap_url[$key]['pengumumanlelang'] = $value->link_pengumuman;
			$scrap_url[$key]['pemenang']         = $value->link_pemenang;
			$scrap_url[$key]['kode_lelang']      = $value->kode_tender;
			$scrap_url[$key]['tier']             = $value->tier;*/
		}

		foreach ($scrap_url as $key => $value)
		{
			$time_start = microtime(true); 

			$header    = array('User-Agent: '.$this->useragent->generate());
			$http_code = $this->scrapping->url_check($value['pengumumanlelang'], $header);
			
			$time_end = microtime(true);
			$new_tender['kode_tender'] = $value['kode_lelang'];

			if ($http_code == '200')
			{
				$header = array('User-Agent: '.$this->useragent->generate());

				$raw_data_pengumuman        = $this->scrapping->get_data_curl($value['pengumumanlelang'], $header);
				$structured_data_pengumuman = $this->scrapping->get_data_string($raw_data_pengumuman, "//tr/td");

				$raw_data_pemenang        = $this->scrapping->get_data_curl($value['pemenang'], $header);
				$structured_data_pemenang = $this->scrapping->get_data_string($raw_data_pemenang, "//tr/td/table/tr/td");

				//Menghapus informasi RUP
				if (!empty($structured_data_pengumuman[2]))
				{
					unset($structured_data_pengumuman[2]);
					unset($structured_data_pengumuman[3]);
					unset($structured_data_pengumuman[4]);
					unset($structured_data_pengumuman[5]);

					$structured_data_pengumuman = array_values($structured_data_pengumuman);
				}

				$sistem_pengadaan = explode(' - ', trim($structured_data_pengumuman[9]));

				$new_tender['nama_tender']           = $this->opentender_lib->cleaning_format($structured_data_pengumuman[1]);
				$new_tender['tanggal_pembuatan']     = $this->opentender_lib->cleaning_format($structured_data_pengumuman[3], 'tanggal');
				$new_tender['tahap_tender']          = $this->opentender_lib->cleaning_format($structured_data_pengumuman[5]);
				$new_tender['instansi']              = $this->opentender_lib->cleaning_format($structured_data_pengumuman[6]);
				$new_tender['satuan_kerja']          = $this->opentender_lib->cleaning_format($structured_data_pengumuman[7]);
				$new_tender['kategori']              = $this->opentender_lib->cleaning_format($structured_data_pengumuman[8]);
				$new_tender['sistem_pengadaan']      = $this->opentender_lib->cleaning_format($structured_data_pengumuman[9]);
				$new_tender['metode_pemilihan']      = $this->opentender_lib->cleaning_format($sistem_pengadaan[0]);
				$new_tender['penilaian_kualifikasi'] = $this->opentender_lib->cleaning_format($sistem_pengadaan[1]);
				$new_tender['metode_evaluasi']       = $this->opentender_lib->cleaning_format($sistem_pengadaan[2]);
				$new_tender['tahun_anggaran']        = $this->opentender_lib->cleaning_format($structured_data_pengumuman[10]);
				$new_tender['nilai_pagu_paket']      = $this->opentender_lib->cleaning_format($structured_data_pengumuman[11], 'uang');
				$new_tender['nilai_hps_paket']       = $this->opentender_lib->cleaning_format($structured_data_pengumuman[12], 'uang');
				$new_tender['jumlah_peserta']        = $this->opentender_lib->cleaning_format(end($structured_data_pengumuman), 'peserta');
				$new_tender['nama_pemenang']         = strtoupper($this->opentender_lib->cleaning_format($structured_data_pemenang[0]));
				$new_tender['alamat_pemenang']       = $this->opentender_lib->cleaning_format($structured_data_pemenang[1]);
				$new_tender['npwp_pemenang']         = $this->opentender_lib->cleaning_format($structured_data_pemenang[2]);

				if (isset($structured_data_pemenang[3]))
					$new_tender['harga_penawaran'] = $this->opentender_lib->cleaning_format($structured_data_pemenang[3], 'uang');
				else
					$new_tender['harga_penawaran'] = 'N/A';

				if (isset($structured_data_pemenang[4]))
					$new_tender['harga_terkoreksi'] = $this->opentender_lib->cleaning_format($structured_data_pemenang[4], 'uang');
				else
					$new_tender['harga_terkoreksi'] = 'N/A';

				if (isset($structured_data_pemenang[5]))
					$new_tender['harga_negosisasi'] = $this->opentender_lib->cleaning_format($structured_data_pemenang[5], 'uang');
				else
					$new_tender['harga_negosisasi'] = 'N/A';
			}

			$new_tender['link_pengumuman'] = $value['pengumumanlelang'];
			$new_tender['link_pemenang']   = $value['pemenang'];
			$new_tender['http_code']       = $http_code;
			$new_tender['execution_time']  = $time_end - $time_start;
			$new_tender['timestamp']       = date('Y-m-d H:i:s');
			$new_tender['tier']            = $value['tier'];

			$time_processing += ($time_end - $time_start);

			$this->db->insert('tbl_rescrap_lpse', $new_tender);

			// Update
			/*$this->db->where('kode_tender', $value['kode_lelang']);
			$this->db->update('tbl_rescrap_lpse', $new_tender);*/

			$new_tender = array();

			sleep(0.1);
		}

		$metadata['affected_rows'] = $this->db->affected_rows();
		$metadata['status'] = 'Done';
		$metadata['time_processing'] = $time_processing;

		echo "<pre>";
		print_r ($metadata);
		echo "</pre>";
	}

	public function scrap_api_ot()
	{
		$tier = $this->input->get('tier');

		$this->db->select("tier, kode_paket, kode_lelang, SUBSTRING_INDEX(opentender_link,'/',-1) kode_opentender");
		$this->db->where('tier', $tier);
		$kode_opentender = $this->db->get('tbl_datasource_opentender')->result();

		foreach ($kode_opentender as $key => $value)
		{
			$time_start = microtime(true); 
			$url    = 'https://v3.opentender.net/api/tender/'.$value->kode_opentender;
			$header = array('User-Agent: '.$this->useragent->generate(), 'Content-Type: application/json');
			$time_end = microtime(true);
			
			$http_code = $this->scrapping->url_check($url, $header);

			if ($http_code == '200')
			{
				$json = $this->scrapping->get_data_curl($url, $header, null, 'GET');

				$data = array(
					'api_opentender' => $json, 
					'timestamp' => date('Y-m-d H:i:s'),
					'execution_time' => $time_end - $time_start,
					'kode_lelang' => $value->kode_lelang,
					'kode_paket' => $value->kode_paket,
					'kode_opentender' => $value->kode_opentender,
					'tier' => $value->tier
				);

				$this->db->insert('tbl_rescrap_opentender', $data);
			}
			else
			{
				$data = array(
					'api_opentender' => null, 
					'timestamp' => date('Y-m-d H:i:s'),
					'execution_time' => $time_end - $time_start,
					'kode_lelang' => $value->kode_lelang,
					'kode_paket' => $value->kode_paket,
					'kode_opentender' => $value->kode_opentender,
					'tier' => $value->tier
				);

				$this->db->insert('tbl_rescrap_opentender', $data);
			}

			sleep(0.2);
		}

		echo 'Done !';
	}

	public function update_ot()
	{
		$this->db->select('kode_opentender');
		$this->db->where('api_opentender', null);
		$kode_opentender = $this->db->get('tbl_rescrap_opentender')->result();

		foreach ($kode_opentender as $key => $value)
		{
			$time_start = microtime(true); 
			$url    = 'https://v3.opentender.net/api/tender/'.$value->kode_opentender;
			$header = array('User-Agent: '.$this->useragent->generate(), 'Content-Type: application/json');
			$time_end = microtime(true);
			
			$json = $this->scrapping->get_data_curl($url, $header, null, 'GET');
			$data = array(
				'api_opentender' => $json, 
				'timestamp' => date('Y-m-d H:i:s'),
				'execution_time' => $time_end - $time_start,
			);

			$this->db->where('kode_opentender', $value->kode_opentender);
			$this->db->update('tbl_rescrap_opentender', $data);

			sleep(0.2);
		}

		echo 'Done !';
	}

	public function trial_text_analysis()
	{
		$this->db->select('auction_code kode_lelang, package_name_edit judul_paket');
		$this->db->where('weight_pendidikan', null);
		$this->db->order_by('RAND ()');
		$data = $this->db->get('tbl_ta_opentender', 3000)->result();

		$terms['pendidikan']  = array('guru','kelas','gedung','sd','smp','sma','lab','komputer','laboratorium','bahasa','modul','alat_peraga','pendidikan','meja','kursi','buku','perpustakaan');
		$weight['pendidikan'] = array(0.09,0.08,0.02,0.08,0.08,0.07,0.06,0.02,0.06,0.07,0.06,0.07,0.05,0.03,0.03,0.05,0.08);

		$terms['kesehatan']   = array('kesehatan','dokter','bidan','obat','perawat','alat_kesehatan','rumah_sakit','rawat','ambulan','pasien');
		$weight['kesehatan']  = array(0.05,0.20,0.10,0.10,0.10,0.10,0.10,0.10,0.05,0.10);

		$terms['ekonomi']     = array('kaki_lima','pmks','umkm','psk','disabilitas','cacat','rumah_tangga_miskin','miskin','kemiskinan');
		$weight['ekonomi']    = array(0.12,0.12,0.12,0.10,0.10,0.10,0.10,0.12,0.12);

		foreach ($data as $key => $value)
		{
			$judul_paket    = strtolower(trim($value->judul_paket));
			$sentence_split = explode(' ', $judul_paket);
			$weight_value   = 0;
			$total_weight   = 0;

			foreach ($terms as $context => $term)
			{
				$total_weight = 0;
				foreach ($sentence_split as $key => $word)
				{
					$index_terms = array_search($word, $terms[$context]);

					if (is_int($index_terms))
					{
						$weight_value = $weight[$context][$index_terms];
						$total_weight += $weight_value;
					}

					$result['weight_'.$context] = $total_weight;
				}
			}

			$this->db->where('auction_code', $value->kode_lelang);
			$this->db->update('tbl_ta_opentender', $result);
		}

		echo $this->db->last_query();
	}

	public function text_analysis()
	{
		$table_name = 'tbl_final_opentender_02';
		$terms      = $this->opentender_lib->reformat_dictionary('http://localhost/projects/scrapping/assets/dump-json/ta_weight.json');
		$counter    = 0;

		for ($i=0; $i < 10; $i++)
			$temp_table[] = 'temp_tbl_'.substr(md5(rand(0,999)), 0, 5);

		//Create Temporary Table
		for ($j=0; $j < 50000; $j+=5000)
		{ 
			$query = 'CREATE TABLE '.$temp_table[$counter].' AS SELECT * FROM '.$table_name.' LIMIT 5000 OFFSET '.$j;
			$result['create_table_result'][$counter] = $this->db->query($query);
			$counter++;
		}

		//Text Analysis
		foreach ($temp_table as $table)
		{
			//Get Data
			$this->db->select('kode_lelang, judul_paket_edit as judul_paket');
			$this->db->where('text_analysis IS NULL');
			$data = $this->db->get($table)->result();

			foreach ($data as $keys => $sentence)
			{
				$judul_paket    = strtolower(trim($sentence->judul_paket));
				$sentence_split = explode(' ', $judul_paket);
				$weight_value   = 0;
				$total_weight   = 0;

				foreach ($terms as $context => $term)
				{
					$total_weight = 0;
					foreach ($sentence_split as $key => $word)
					{
						$to_replace  = array('/','(',')');
						$clean_word  = str_replace($to_replace, '', $word);
						$index_terms = array_search($clean_word, $terms[$context]['terms']);

						if (is_int($index_terms))
						{
							$weight_value = $terms[$context]['weight'][$index_terms];;
							$total_weight += $weight_value;
						}

						//Result Metadata
						$result['context'][$context] = $total_weight;
					}
				}

				//Result Metadata
				$result['terms']           = $sentence_split;
				$result['number_of_terms'] = count($sentence_split);
				$result['timestamp']       = date('Y-m-d H:i:s');

				//Update data properties
				$update_data['text_analysis']     = json_encode($result);
				$update_data['pendidikan_weight'] = $result['context']['pendidikan'];
				$update_data['kesehatan_weight']  = $result['context']['kesehatan'];
				$update_data['ekonomi_weight']    = $result['context']['ekonomi'];

				//Update data execution
				$this->db->where('kode_lelang', $sentence->kode_lelang);
				$this->db->update($table, $update_data);
			}
		}

		// Merge Result to Other Table
		foreach ($temp_table as $table)
			$result['merge_table_result'][] = $this->db->query('INSERT INTO tbl_final_opentender_temp SELECT * FROM '.$table);

		// Delete Temporary Table
		foreach ($temp_table as $table)
			$result['delete_table_result'][] = $this->db->query('DROP TABLE '.$table);

		$this->output->set_content_type('application/json')->set_output(json_encode($result));
	}

	public function do_text_analysis()
	{
		$counter    = 0;
		$table_name = 'tbl_final_opentender_03';

		for ($i=0; $i < 10; $i++)
			$temp_table[] = 'temp_tbl_'.substr(md5(rand(0,999)), 0, 5);

		//Create Temporary Table
		for ($j=0; $j < 50000; $j+=5000)
		{ 
			$query = 'CREATE TABLE '.$temp_table[$counter].' AS SELECT * FROM '.$table_name.' LIMIT 5000 OFFSET '.$j;
			$create_table_result[$counter] = $this->db->query($query);
			$counter++;
		}

		// Merge Result to Other Table
		foreach ($temp_table as $table)
			$merge_table_result[] = $this->db->query('INSERT INTO tbl_final_opentender_00 SELECT * FROM '.$table);

		// Delete Temporary Table
		foreach ($temp_table as $table)
			$delete_table_result[] = $this->db->query('DROP TABLE '.$table);
	}

	public function text_analysis_birms()
	{
		$table = 'tbl_birms';
		$terms = $this->opentender_lib->reformat_dictionary('http://localhost/projects/scrapping/assets/dump-json/ta_weight.json');

		$this->db->select('ocid kode_lelang, nama_tender_edit as judul_paket');
		$this->db->where('pendidikan_weight IS NULL');
		$this->db->where('jumlah_pemenang > 0');
		$data = $this->db->get($table)->result();

		foreach ($data as $keys => $sentence)
		{
			$judul_paket    = strtolower(trim($sentence->judul_paket));
			$sentence_split = explode(' ', $judul_paket);
			$weight_value   = 0;
			$total_weight   = 0;

			foreach ($terms as $context => $term)
			{
				$total_weight = 0;
				foreach ($sentence_split as $key => $word)
				{
					$to_replace  = array('/','(',')');
					$clean_word  = str_replace($to_replace, '', $word);
					$index_terms = array_search($clean_word, $terms[$context]['terms']);

					if (is_int($index_terms))
					{
						$weight_value = $terms[$context]['weight'][$index_terms];;
						$total_weight += $weight_value;
					}

					//Result Metadata
					$result['context'][$context] = $total_weight;
				}
			}

			//Result Metadata
			$result['terms']           = $sentence_split;
			$result['number_of_terms'] = count($sentence_split);
			$result['timestamp']       = date('Y-m-d H:i:s');

			//Update data properties
			// $update_data['text_analysis']     = json_encode($result);
			$update_data['pendidikan_weight'] = $result['context']['pendidikan'];
			$update_data['kesehatan_weight']  = $result['context']['kesehatan'];
			$update_data['ekonomi_weight']    = $result['context']['ekonomi'];

			//Update data execution
			$this->db->where('ocid', $sentence->kode_lelang);
			$this->db->update($table, $update_data);
		}
	}

	public function lpse_dump()
	{
		ini_set('memory_limit', '512M');

		$dir = scandir('D:\xampp\htdocs\projects\srpp\assets\dump-lpse');

		unset($dir[0]);
		unset($dir[1]);

		$dir = array_values($dir);
		foreach ($dir as $key => $file)
		{
			$url = 'http://localhost/projects/srpp/assets/dump-lpse/'.$file;

			if ($file == 'prov-bangkabelitung.json')
			{
				$data = file_get_contents($url);
				$data = json_decode($data, TRUE);

				foreach ($data['data'] as $detail_tender)
				{
					$kategori_ta    = explode('-', $detail_tender[8]);
					$kategori       = trim($kategori_ta[0]);
					$tahun_anggaran = trim($kategori_ta[1]);

					$nilai_kontrak = $detail_tender[10];
					if ($nilai_kontrak != 'Nilai Kontrak belum dibuat')
						$nilai_kontrak = str_replace('.', '', substr($nilai_kontrak, 3,-3));

					$tender['kode_tender']           = $detail_tender[0];
					$tender['judul_tender']          = strip_tags($detail_tender[1]);
					$tender['satuan_kerja']          = $detail_tender[2];
					$tender['tahap_tender']          = $detail_tender[3];
					$tender['pagu_tender']           = $detail_tender[4];
					$tender['penilaian_kualifikasi'] = $detail_tender[5];
					$tender['metode_pemilihan']      = $detail_tender[6];
					$tender['metode_evaluasi']       = $detail_tender[7];
					$tender['kategori']              = $kategori;
					$tender['tahun_anggaran']        = $tahun_anggaran;
					$tender['nilai_kontrak']         = $nilai_kontrak;
					$tender['nama_file']             = $file;

					$this->db->insert('tbl_dump_lpse_02', $tender);
				}
			}
		}
	}

	public function text_analysis_lpse()
	{
		$dictionary = 'http://localhost/projects/scrapping/assets/dump-json/ta_weight.1.1.json';
		$terms      = $this->opentender_lib->reformat_dictionary($dictionary);
		$table_name = 'tbl_dump_lpse_02';
		$total      = 282268;
		// $total      = 10000;
		$counter    = 0;

		for ($i=0; $i < $total; $i+=5000)
		{ 
			$temp_table[$counter] = 'temp_table_'.$counter;
			$query = 'CREATE TABLE '.$temp_table[$counter].' AS SELECT * FROM '.$table_name.' LIMIT 5000 OFFSET '.$i;
			$this->db->query($query);

			$counter++;
		}

		foreach ($temp_table as $table)
		{
			//Get Data
			$this->db->select('kode_tender, judul_tender as judul_paket');
			// $this->db->where('text_analysis IS NULL');
			$data = $this->db->get($table)->result();

			foreach ($data as $keys => $sentence)
			{
				$judul_paket    = strtolower(trim($sentence->judul_paket));
				$sentence_split = explode(' ', $judul_paket);
				$weight_value   = 0;
				$total_weight   = 0;

				foreach ($terms as $context => $term)
				{
					$total_weight = 0;
					foreach ($sentence_split as $key => $word)
					{
						/*$to_replace  = array('/','(',')');
						$clean_word  = str_replace($to_replace, '', $word);*/

						$clean_word  = preg_replace("/[^ \w]+/", " ", $word);
						$index_terms = array_search($clean_word, $terms[$context]['terms']);

						if (is_int($index_terms))
						{
							$weight_value = $terms[$context]['weight'][$index_terms];;
							$total_weight += $weight_value;
						}

						//Result Metadata
						$result['context'][$context] = $total_weight;
					}
				}

				//Result Metadata
				$result['terms']           = $sentence_split;
				$result['number_of_terms'] = count($sentence_split);
				$result['timestamp']       = date('Y-m-d H:i:s');

				//Update data properties
				// $update_data['text_analysis']     = json_encode($result);
				$update_data['pendidikan_weight'] = $result['context']['pendidikan'];
				$update_data['kesehatan_weight']  = $result['context']['kesehatan'];
				$update_data['ekonomi_weight']    = $result['context']['ekonomi'];

				//Update data execution
				$this->db->where('kode_tender', $sentence->kode_tender);
				$this->db->update($table, $update_data);
			}
		}

		// Merge Result to Other Table
		foreach ($temp_table as $table)
			$result['merge_table_result'][] = $this->db->query('INSERT INTO tbl_final_lpse_02 SELECT * FROM '.$table);

		// Delete Temporary Table
		foreach ($temp_table as $table)
			$result['delete_table_result'][] = $this->db->query('DROP TABLE '.$table);

		echo "DONE!";
	}

	public function rescrap()
	{
		$time_processing = 0;
		$this->db->select('kode_tender,url,pendidikan_weight,ekonomi_weight,kesehatan_weight');
		$result = $this->db->get('tbl_final_lpse_labeled')->result();

		foreach ($result as $key => $properties)
		{
			$new_prop[$key]['kode_tender']             = $properties->kode_tender;
			$new_prop[$key]['url']                     = $properties->url;
			$new_prop[$key]['url_pengumuman']          = $properties->url.'/lelang/'.$properties->kode_tender.'/pengumumanlelang';
			$new_prop[$key]['url_pemenang']            = $properties->url.'/evaluasi/'.$properties->kode_tender.'/pemenang';
			$new_prop[$key]['ta_result']['kesehatan']  = $properties->kesehatan_weight;
			$new_prop[$key]['ta_result']['pendidikan'] = $properties->pendidikan_weight;
			$new_prop[$key]['ta_result']['ekonomi']    = $properties->ekonomi_weight;
		}

		foreach ($new_prop as $key => $value)
		{
			$time_start = microtime(true); 

			$header    = array('User-Agent: '.$this->useragent->generate());
			$http_code = $this->scrapping->url_check($value['url_pengumuman'], $header);
			
			$time_end = microtime(true);
			$new_tender['kode_tender'] = $value['kode_tender'];

			if ($http_code == '200')
			{
				$header = array('User-Agent: '.$this->useragent->generate());

				$raw_data_pengumuman        = $this->scrapping->get_data_curl($value['url_pengumuman'], $header);
				$structured_data_pengumuman = $this->scrapping->get_data_string($raw_data_pengumuman, "//tr/td");

				$raw_data_pemenang        = $this->scrapping->get_data_curl($value['url_pemenang'], $header);
				$structured_data_pemenang = $this->scrapping->get_data_string($raw_data_pemenang, "//tr/td/table/tr/td");

				//Menghapus informasi RUP
				if (!empty($structured_data_pengumuman[2]))
				{
					unset($structured_data_pengumuman[2]);
					unset($structured_data_pengumuman[3]);
					unset($structured_data_pengumuman[4]);
					unset($structured_data_pengumuman[5]);
					
					$structured_data_pengumuman = array_values($structured_data_pengumuman);
				}
				
				$sistem_pengadaan = explode(' - ', trim($structured_data_pengumuman[9]));
				
				//Ketika lelang memiliki "Lingkup Pekerjaan"
				if (count($sistem_pengadaan) == 1)
				{
					unset($structured_data_pengumuman[5]);
					$structured_data_pengumuman = array_values($structured_data_pengumuman);

					$sistem_pengadaan = explode(' - ', trim($structured_data_pengumuman[9]));
				}

				$new_tender['nama_tender']           = $this->opentender_lib->cleaning_format($structured_data_pengumuman[1]);
				$new_tender['tanggal_pembuatan']     = $this->opentender_lib->cleaning_format($structured_data_pengumuman[3], 'tanggal');
				$new_tender['tahap_tender']          = $this->opentender_lib->cleaning_format($structured_data_pengumuman[5]);
				$new_tender['instansi']              = $this->opentender_lib->cleaning_format($structured_data_pengumuman[6]);
				$new_tender['satuan_kerja']          = $this->opentender_lib->cleaning_format($structured_data_pengumuman[7]);
				$new_tender['kategori']              = $this->opentender_lib->cleaning_format($structured_data_pengumuman[8]);
				$new_tender['sistem_pengadaan']      = $this->opentender_lib->cleaning_format($structured_data_pengumuman[9]);
				$new_tender['metode_pemilihan']      = $this->opentender_lib->cleaning_format($sistem_pengadaan[0]);
				$new_tender['penilaian_kualifikasi'] = $this->opentender_lib->cleaning_format($sistem_pengadaan[1]);
				$new_tender['metode_evaluasi']       = $this->opentender_lib->cleaning_format($sistem_pengadaan[2]);
				$new_tender['tahun_anggaran']        = $this->opentender_lib->cleaning_format($structured_data_pengumuman[10]);
				$new_tender['nilai_pagu_paket']      = $this->opentender_lib->cleaning_format($structured_data_pengumuman[11], 'uang');
				$new_tender['nilai_hps_paket']       = $this->opentender_lib->cleaning_format($structured_data_pengumuman[12], 'uang');
				$new_tender['jumlah_peserta']        = $this->opentender_lib->cleaning_format(end($structured_data_pengumuman), 'peserta');
				$new_tender['nama_pemenang']         = strtoupper($this->opentender_lib->cleaning_format($structured_data_pemenang[0]));
				$new_tender['alamat_pemenang']       = $this->opentender_lib->cleaning_format($structured_data_pemenang[1]);
				$new_tender['npwp_pemenang']         = $this->opentender_lib->cleaning_format($structured_data_pemenang[2]);

				if (isset($structured_data_pemenang[3]))
					$new_tender['harga_penawaran'] = $this->opentender_lib->cleaning_format($structured_data_pemenang[3], 'uang');
				else
					$new_tender['harga_penawaran'] = 'N/A';

				if (isset($structured_data_pemenang[4]))
					$new_tender['harga_terkoreksi'] = $this->opentender_lib->cleaning_format($structured_data_pemenang[4], 'uang');
				else
					$new_tender['harga_terkoreksi'] = 'N/A';

				if (isset($structured_data_pemenang[5]))
					$new_tender['harga_negosisasi'] = $this->opentender_lib->cleaning_format($structured_data_pemenang[5], 'uang');
				else
					$new_tender['harga_negosisasi'] = 'N/A';
				
			}

			$new_tender['link_pengumuman'] = $value['url_pengumuman'];
			$new_tender['link_pemenang']   = $value['url_pemenang'];
			$new_tender['http_code']       = $http_code;
			$new_tender['execution_time']  = $time_end - $time_start;
			$new_tender['timestamp']       = date('Y-m-d H:i:s');
			$new_tender['ta_result']       = json_encode($value['ta_result']);

			$time_processing += ($time_end - $time_start);

			$this->db->insert('tbl_lpse_rescrap', $new_tender);

			$new_tender = array();

			sleep(0.1);
		}
	}

	public function terms_explode()
	{
		ini_set('memory_limit', '512M');

		$this->db->select('kode_tender, judul_tender');
		$tender = $this->db->get('tbl_final_lpse_00', 100000, 200000)->result();

		foreach ($tender as $keys => $values)
		{
			$judul_tender = preg_replace("/[^ \w]+/", " ", $values->judul_tender);
			// $judul_tender = str_replace('  ', ' ', $judul_tender);

			// $judul_tender = $values->judul_tender;
			$term = explode(' ', $judul_tender);

			$i = 0;
			foreach ($term as $key => $value)
			{
				if (!empty($value) AND !is_numeric($value))
				{
					$data['kode_tender']  = $values->kode_tender;
					$data['judul_tender'] = $values->judul_tender;
					$data['term_order']   = $i;
					$data['term']         = trim(strtolower($value));

					$this->db->insert('tbl_terms_lpse', $data);

					$i++;
				}
			}
		}

		echo "DONE !";
	}

	//--Begin Part 2--//

	public function monitor($page = null, $year = null)
	{
		if ($page == 'detail')
			$data['detail_stats'] = $this->db->get('v_detail_monitor_'.$year)->result();
		else
			$data['statistic'] = $this->db->get('v_monitor_statistic')->result();

		$this->load->view('v_monitor', $data);
	}

	public function domain_check_new()
	{
		$http_code = $this->input->get('http_code');
		
		$this->db->where('http_code', $http_code);
		$this->db->select('id_record, url');
		$result = $this->db->get('domain_check')->result();

		foreach ($result as $key => $value)
		{
			$is_url = filter_var($value->url, FILTER_VALIDATE_URL);
			if ($is_url)
			{
				$status = $this->opentender_lib->url_check($value->url);

				$this->db->where('id_record', $value->id_record);
				$this->db->update('domain_check', array('http_code' => $status));

				sleep(0.2);
			}
		}
	}

	public function rescrap_new()
	{
		$num  = $this->input->get('num');
		$year = $this->input->get('year');
		$tier = $this->input->get('tier');
		$retry = $this->input->get('retry');

		$this->db->select('kd_lelang, link_pengumumanlelang, link_pemenang, link_pemenangberkontrak');
		$this->db->where('link_pengumumanlelang IS NOT NULL');

		if (isset($tier))
			$this->db->where('tier', $tier);

		if (isset($retry) AND ($retry == true))
			$this->db->where('link_http_code = 0 OR (link_http_code IS NULL AND link_pengumumanlelang IS NOT NULL)');
		else
			$this->db->where('link_http_code IS NULL');
		
		if (isset($num))
			$this->db->limit($num);

		$list_url = $this->db->get('lelang_rescrap_'.$year)->result();

		foreach ($list_url as $key => $value)
		{
			$time_start = microtime(true);
			$header     = array('User-Agent: '.$this->useragent->generate());
			$http_code  = $this->scrapping->url_check($value->link_pengumumanlelang, $header);

			if ($http_code == 200)
			{
				$result_data['kd_lelang']          = $value->kd_lelang;
				$result_data['pengumumanlelang']   = $this->opentender_lib->get_tender_data($value->link_pengumumanlelang, $header, "//tr/td");
				$result_data['pemenang']           = $this->opentender_lib->get_tender_data($value->link_pemenang, $header, "//tr/td");
				$result_data['pemenangberkontrak'] = $this->opentender_lib->get_tender_data($value->link_pemenangberkontrak, $header, "//tr/td");
				$result_data['scrapped']           = date('Y-m-d H:i:s');
				$result_data['tahun']              = $year;
				
				$time_end = microtime(true);
				$result_data['execution_time'] = $time_end - $time_start;

				$this->db->insert('lelang_rescrap_result_'.$year, $result_data);

				sleep(0.05);
			}

			$time_end = microtime(true);

			$log_data['link_http_code'] = $http_code;
			$log_data['scrapped']       = date('Y-m-d H:i:s');
			$log_data['execution_time'] = $time_end - $time_start;

			$this->db->where('kd_lelang', $value->kd_lelang);
			$this->db->update('lelang_rescrap_'.$year, $log_data);
		}

		$this->output->set_content_type('application/json')->set_output(json_encode($result_data));
	}

	public function autoloop()
	{
		$year = $this->input->get('year');
		$debug = $this->input->get('debug');

		$this->db->select('id, kd_lelang, scrapped, tier');
		$this->db->where('link_http_code IS NOT NULL');
		$this->db->order_by('tier', 'desc');
		$this->db->order_by('scrapped', 'desc');
		$result = $this->db->get('lelang_rescrap_'.$year, 1)->row();

		$this->db->select('tier');
		$this->db->order_by('tier', 'desc');
		$this->db->distinct();
		$max_tier = $this->db->get('lelang_rescrap_'.$year, 1)->row();

		$interval  = (strtotime(date('Y-m-d H:i:s')) - strtotime($result->scrapped))/60;
		if ($interval >= 5)
		{
			$tier      = $result->tier;
			$next_tier = $tier+1;

			if ($next_tier <= $max_tier->tier)
			{
				$this->db->select('tier, target, scrapped');
				$this->db->where('tier', $tier);
				$data = $this->db->get('v_detail_monitor_'.$year)->row();

				$last_scrapped    = $result->scrapped;
				$percent_scrapped = $data->scrapped/$data->target;

				if ($percent_scrapped < 1)
					$cmd = "curl --request GET 'http://localhost/index.php/opentender/rescrap_new?year=".$year."&tier=".$tier."'";
				else
					$cmd = "curl --request GET 'http://localhost/index.php/opentender/rescrap_new?year=".$year."&tier=".$next_tier."'";

				if (!isset($debug))
					echo shell_exec($cmd);
				else
					echo $cmd;
			}
		}
	}
}

/* End of file Opentender.php */
/* Location: ./application/controllers/Opentender.php */