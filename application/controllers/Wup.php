<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/*define('DUPLICATED_PURCHASE', 'duplicated_purchase_number');
define('INTERNAL_ERROR', 'internal_error');*/


class Wup extends CI_Controller {

	private $hostname;
	private $username;
	private $password;
	private $remote_folder;
	private $file;
	private $customers_file;
	private $products_file;
	private $sales_file;
	private $debug;
	private $apikey;
	private $pending_folder;
	private $processed_folder;
	private $delimiter;
	private $orders = [];

	public function __construct(){
		parent::__construct();
		$config = $this->config->item('woowup');

		$this->hostname = $config["ftp"]["host"];
		$this->username = $config["ftp"]["user"];
		$this->password = $config["ftp"]["pass"];
		$this->remote_folder = $config["ftp"]["remote_folder"];
		$this->apikey = ($config["sandbox"] == true) ? $config['apikey']["sandbox"] : $config['apikey']["production"];
		$this->debug = $config["debug"];
		$this->pending_folder = ($config["sandbox"] == true) ? $config['folders']["sandbox"]['pending'] : $config['folders']["production"]['pending'];
		$this->processed_folder = ($config["sandbox"] == true) ? $config['folders']["sandbox"]['processed'] : $config['folders']["production"]['processed'];
		$this->ext = $config["file"]["extension"];
		$this->delimiter = $config["file"]["delimiter"];
		$this->customers_file = $config['entities']["customers"];
		$this->products_file = $config['entities']["products"];
		$this->sales_file = $config['entities']["orders"];

		if(!$this->input->is_cli_request()){
			die('No direct script access allowed');
		}

	}


	public function index(){
		die('fin');
	}

	public function sales()
	{

		$this->load->helper('directory');
		$this->load->helper('file');
		$this->load->helper('date');
		$this->load->helper('html');

		$this->file = $this->sales_file.date('Ymd');

		if($this->_downloadFTP()){

			$file_arr = explode(PHP_EOL,read_file($this->pending_folder.'/'.$this->file.'.'.$this->ext));


			$total_rows = count($file_arr)-1;
			$row_index = 0;
			$invoice_number = false;
			$order = [];
			$i;
			foreach ($file_arr as $key => $value) {
				$file_row = str_getcsv($value);

				if(!empty($file_row[1])){
					if(  !$invoice_number == false
						 && isset($file_row[0])
						 && $invoice_number != $file_row[1]
					   ) {
						$order = $this->buildOrder($orders);
						$orders = [];


					} elseif($total_rows == $key) {



						$order = $this->buildOrder($orders);
						$orders = [];
					}

					if(isset($order['prices']) && $order['prices']['total']>0){

						$woowup = new \WoowUp\Client($this->apikey);

						if($woowup->users->exist($order['service_uid'])){
							if($this->debug){
								echo '<pre>';
								print_r($order['invoice_number']);
								echo '</pre>';
							}

							$woowup->purchases->create($order);
						} else {
							if($this->debug){
								echo '<pre>';
								echo "Customer no existe : ".$order['service_uid'];
								echo '</pre>';
							}
						}

					}
					$order = '';
					$invoice_number = isset($file_row[1]) ? $file_row[1] : false;
					$orders[] = $file_row;
				}

			}
			rename($this->pending_folder.'/'.$this->file.'.'.$this->ext, $this->processed_folder.'/'.$this->file.'.'.$this->ext);
		}
		die('FIN');

	}


	public function buildOrder($orders)
	{

		$order = [
		"service_uid" => utf8_encode($orders[0][0]),
		"invoice_number" => $orders[0][1],
		"purchase_detail" => [],
		"prices" => [],
		];

		$cost = 0;
		$shipping = 0;
		$discount = 0;
		$total = 0;
		$tax = 0;


		foreach ($orders as $o) {



			$d = ((int)$o[4] * (float)$o[5])*((float) $o[6]/100);

			if((int)$o[2] >0){

				$order['purchase_detail'][] = [
					"sku" => $o[2],
					"product_name" => utf8_encode($o[3]),
					"discount" => $d,
					"quantity" => (int) ($o[5]),
					"unit_price" => (float) $o[6],
				];

				$cost +=  (float)$o[5] * (float) $o[6];
				$discount += $d;
				$total += (float) $o[8];
			}
		}

		$order['prices'] = [
			"discount" => $discount,
			"total" =>  $total
		];

		return $order;

	}

	public function products($hist = false)
	{
		ini_set('max_execution_time', 0);
		set_time_limit(0);

		$this->load->helper('directory');
		$this->load->helper('file');
		$this->load->helper('html');

		$this->file = $this->products_file;

		if($hist == true){
			$this->file = $this->file."_hist";
		}


		if($this->_downloadFTP()){
			$i = 1;
			$file_arr = explode(PHP_EOL,read_file($this->pending_folder.'/'.$this->file.'.'.$this->ext));
			array_pop($file_arr);

			$products = [];
			$woowup = new \WoowUp\Client($this->apikey);
			foreach ($file_arr as $key => $value) {
				$file_row = str_getcsv($value,$this->deliminter);

				$sku = $file_row[0];
				$item = ['sku' => $file_row[0],
						 'brand' => $file_row[1],
						 'description' => utf8_encode($file_row[2]),
						 'name' => utf8_encode($file_row[3]),
						 'stock'=> (int)$file_row[4],
						 'price'=> (int)$file_row[5]
						];

				if($woowup->products->exist($file_row[0])){
					$woowup->products->update($sku, $item);
					if($this->debug){
						echo '<pre>';
						print_r($i.') update');
						echo '</pre>';
					}
				} else {

					$woowup->products->create($item);
					if($this->debug){
						echo '<pre>';
						print_r($i.') create');
						echo '</pre>';
					}
				}

				$i++;

			}
			rename($this->pending_folder.'/'.$this->file.'.'.$this->ext, $this->processed_folder.'/'.$this->file.'.'.$this->ext);
		}
		die('FIN');

	}

	public function customers($hist = "")
	{
		ini_set('max_execution_time', 0);
		set_time_limit(0);

		$this->load->helper('directory');
		$this->load->helper('file');
		$this->load->helper('html');
		$this->load->helper('email');

		$this->file = $this->customers_file;

		if($hist === "hist"){
			$this->file = $this->file."_hist";
		}

		if($this->_downloadFTP()){
			$i = 1;
			$file_arr = explode(PHP_EOL,read_file($this->pending_folder.'/'.$this->file.'.'.$this->ext));
			array_pop($file_arr);

			$woowup = new \WoowUp\Client($this->apikey);
			foreach ($file_arr as $key => $value) {
				$file_row = str_getcsv($value,$this->delimiter);


				if(!empty($file_row[0]) && count($file_row) == 10 && !empty($file_row[1])){

					$email = explode('/', $file_row[1]);

					if(valid_email($email[0])) {

						$item = ['service_uid' => $file_row[0],
								 'email' => utf8_decode($email[0]),
								 'first_name' => utf8_encode($file_row[2]),
								 'telephone'=> utf8_decode($file_row[3]),
								 'state' => utf8_decode($file_row[6]),
								 'street' => utf8_decode($file_row[7]),
								 'country'=> utf8_decode($file_row[8]),
								 'postcode' => utf8_decode($file_row[9]),
								];

						if($woowup->users->exist($file_row[0])){
							$woowup->users->update($file_row[0], $item);
							if($this->debug){
								echo '<pre>';
								print_r($i.') update');
								echo '</pre>';
							}
						} else {

							$woowup->users->create($item);
							if($this->debug){
								echo '<pre>';
								print_r($i.') create');
								echo '</pre>';
							}
						}
					}
					$i++;

				} else {
					if($this->debug){
						echo 'Error';
						echo '<pre>';
						print_r($file_row);
						echo '</pre>';
					}

				};


			}
			rename($this->pending_folder.'/'.$this->file.'.'.$this->ext, $this->processed_folder.'/'.$this->file.'.'.$this->ext);
		}

		die('FIN');
	}

	private function _findFile($item){
		$item_array = explode("/", $item);
		if(preg_match('/^'.$this->file.'/', $item_array[3])){
			return $item;
		};
	}



	private function _downloadFTP(){

		$config['hostname'] = $this->hostname;
		$config['username'] = $this->username;
		$config['password'] = $this->password;
		$config['debug']    = $this->debug;

		$this->load->library('ftp');
		$this->ftp->connect($config);
		$files = array_filter(array_map(array($this, '_findFile'),$this->ftp->list_files($this->remote_folder)));

		if(!empty($files)){
			$download = true;

			foreach($files as $file){
				try {
					$download = $this->ftp->download($file, $this->pending_folder.'/'.$this->file.'.'.$this->ext, 'ascii');

					if($download !== true){
						throw new Exception("No se pudo descargar el archivo / Archivo no existe.",1);
					};

					$delete = $this->ftp->delete_file($file);

				} catch(Exception $error) {
					echo $error->getMessage();
				}
			}
		} else {
			$download = false;
		}
		$this->ftp->close();

		return $download;

	}
}
