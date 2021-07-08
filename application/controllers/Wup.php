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
	private $hist = false;

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
		$this->payments_file = $config['entities']["payments"];

		if(!$this->input->is_cli_request()){
			die('No direct script access allowed');
		}

	}


	public function index(){
		die('fin');
	}

	protected function loadPayments($payments) {
		$this->payments = [];
		$payments = $this->pending_folder.'/'.$payments.'.'.$this->ext;

		$fh = fopen($payments, 'r');
		while (($row = fgetcsv($fh, 1000, ',')) !== false) {
			$arr = [
                'invoice_number'    => $row[0],
                'bank'             => $row[1],
                'payment_type'      => strtolower($row[2]),
                'total'             => (float)$row[3],
                'brand'              => $row[4],
            ];
            if (!isset($this->payments[$row[0]])) $this->payments[$row[0]] = [];
			$this->payments[$row[0]][] = $arr;
		}
	}

	public function sales($days = 0, $hist = false)
	{

		$this->load->helper('directory');
		$this->load->helper('file');
		$this->load->helper('date');
		$this->load->helper('html');

		if ($hist == false) {
			$this->file = [
				$this->sales_file.date('Ymd', strtotime("-$days days")),
				$this->payments_file.date('Ymd', strtotime("-$days days")),
			];
			$days++;
			$expected_date = date('Y-m-d 12:00:00', strtotime("-$days days"));
		} else {
			$this->file = "ordershist_20180704125214";
			$this->payments = false;
			$this->hist = true;
		}

		if($this->_downloadFTP()){

			$ordersFile = $this->file[0];
			$payments = $this->file[1];
			$this->loadPayments($payments);
			$file_arr = explode(PHP_EOL,read_file($this->pending_folder.'/'.$ordersFile.'.'.$this->ext));
			$total_rows = count($file_arr)-1;
			$row_index = 0;
			$invoice_number = false;
			$order = [];
			$i;
			foreach ($file_arr as $key => $value) {
				$file_row = str_getcsv($value);
				if (count($file_row) <= 1) {
					continue;
				}
				$sale_date = array_shift($file_row);
				$sale_date = date_format(date_create_from_format("d/m/Y", $sale_date), "Y-m-d 12:00:00");

				if ($hist === false) {
					if ($expected_date !== $sale_date) {
						continue;
					}
				}		

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

						try {
							if($woowup->users->exist($order['service_uid'])){
								if($this->debug){
									echo '<pre>';
									print_r($order['invoice_number']);
									echo '</pre>';
								}

								$order['createtime'] = $sale_date;
								$woowup->purchases->create($order);
							} else {
								if($this->debug){
									echo '<pre>';
									echo "Customer no existe : ".$order['service_uid'];
									echo '</pre>';
								}
							}
						} catch (\Exception $e) {
							if (method_exists($e, "getResponse")) {
								$response = json_decode($e->getResponse()->getBody(), true);
		                		switch ($response['code']) {
		                    		case 'duplicated_purchase_number':
		                    			$woowup->purchases->update($order);
		                    			echo '<pre>';
		                    			print_r($order['invoice_number']);
		                    			echo ' actualizada';
		                    			echo '</pre>';
		                    			break;
		                    		default:
		                    			echo $response['code'];
		                    			break;
		                    	}
							} else {
								var_dump($e->getMessage());
							}
						}
					}
					$order = '';
					$invoice_number = isset($file_row[1]) ? $file_row[1] : false;
					$orders[] = $file_row;
				}

			}
			if (isset($orders) && isset($sale_date)) {
				$order = $this->buildOrder($orders);
				$order['createtime'] = $sale_date;
				$woowup = new \WoowUp\Client($this->apikey);
				try {
					if($woowup->users->exist($order['service_uid'])){
						if($this->debug){
							echo '<pre>';
							print_r($order['invoice_number']);
							echo '</pre>';
						}

						$order['createtime'] = $sale_date;
						$woowup->purchases->create($order);
					} else {
						if($this->debug){
							echo '<pre>';
							echo "Customer no existe : ".$order['service_uid'];
							echo '</pre>';
						}
					}
				} catch (\Exception $e) {
					if (method_exists($e, "getResponse")) {
						$response = json_decode($e->getResponse()->getBody(), true);
                		switch ($response['code']) {
                    		case 'duplicated_purchase_number':
                    			$woowup->purchases->update($order);
                    			echo '<pre>';
                    			print_r($order['invoice_number']);
                    			echo ' actualizada';
                    			echo '</pre>';
                    			break;
                    		default:
                    			echo $response['code'];
                    			break;
                    	}
					} else {
						var_dump($e->getMessage());
					}
				}
			}
			if (is_array($this->file)) {
				foreach ($this->file as $f) {
					rename($this->pending_folder.'/'.$f.'.'.$this->ext, $this->processed_folder.'/'.$f.'.'.$this->ext);
				}
			} else {
				rename($this->pending_folder.'/'.$this->file.'.'.$this->ext, $this->processed_folder.'/'.$this->file.'.'.$this->ext);
			}
		}
		die('FIN');

	}


	public function buildOrder($orders)
	{

		$order = [
		"service_uid" => utf8_encode($orders[0][1]),
		"invoice_number" => $orders[0][2],
		"purchase_detail" => [],
		"prices" => [],
		];

		if (($branch_name = $this->getBranch($orders[0][0])) !== null) {
			$order['branch_name'] = $branch_name;
		}

		$cost = 0;
		$shipping = 0;
		$discount = 0;
		$total = 0;
		$tax = 0;


		foreach ($orders as $o) {

			array_shift($o);

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

		if (isset($this->payments[$order['invoice_number']])) {
			$order['payment'] = [];
			$payments = $this->payments[$order['invoice_number']];
			foreach ($payments as $payment) {
				$paymentArray = [
					'type'	=> strtolower($payment['payment_type']),
					'brand'	=> ucfirst(strtolower($payment['brand'])),
					'bank'	=> ucfirst(strtolower($payment['bank'])),
					'total'	=> (float) $payment['total'],
				];
				$order['payment'][] = $paymentArray;
			}
		}
		return $order;

	}

	public function getBranch($branch_id)
	{
		$branches = [
			'602' => "602 - ARRIBEÑOS",
			'604' => "604 - SOLAR DE LA ABADIA",
			'608' => "608 - DOT BAIRES",
			'609' => "609 - ALTO PALERMO",
			'610' => "610 - CASONA CORDOBA",
			'611' => "611 - LA PLATA (FERUMA)",
			'613' => "613 - RECOLETA MALL",
			'614' => "614 - GALERIAS PACIFICO",
			'615' => "615 - PATIO BULLRICH",
			'650' => "650 - QUINTANA",
			'700' => "999 - OUTLETS",
			'701' => "701 - AGUIRRE",
			'704' => "704 - SOLEIL FACTORY",
			'705' => "705 - DISTRITO ARCOS",
			'801' => "801 - LUXURY OUTLET",
			'802' => "802 - CARPA LM",
			'803' => "803 - CARPA AAP",
			];

		if (isset($branches[$branch_id])) {
			return $branches[$branch_id];
		} else {
			return "$branch_id - DESCONOCIDA";
		}
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
			$this->hist = true;
		}

		if($this->_downloadFTP()){
			$i = 1;
			$file_arr = explode(PHP_EOL,read_file($this->pending_folder.'/'.$this->file.'.'.$this->ext));
			array_pop($file_arr);

			$products = [];
			$woowup = new \WoowUp\Client($this->apikey);

			foreach ($file_arr as $key => $value) {
				$file_row = str_getcsv($value,$this->delimiter);

				if (($file_row[4] == "") || ($file_row[2] == "")) {
					continue;
				}

				$sku = $file_row[0];
				$item = ['sku' => $file_row[0],
						 'brand' => $file_row[1],
						 'description' => utf8_encode($file_row[2]),
						 'name' => utf8_encode($file_row[4])
						];

				// Categorias
				$category = [];
				$parts = explode(" ", utf8_encode($file_row[2]));
				$parentCat = array_shift($parts);
				if (array_search($parentCat, array("LOCAL", "EMBALAJE", "GESTION", "INSUMO")) !== false) {
					continue;
				}
				$category[] = [
					'id' => $parentCat,
					'name' => $parentCat
				];
				if (count($parts) > 0) {
					$subCat = array_shift($parts);
					$category[] = [
						'id' => $parentCat . "-" . $subCat,
						'name' => $subCat
					];
					if (count($parts) > 0) {	
						$subsubCat = implode(" ", $parts);
						$category[] = [
							'id' => $parentCat . "-" . $subCat . "-" . $subsubCat,
							'name' => $subsubCat
						];
					}
				}

				$item['category'] = $category;
				/*
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
				}*/

				if ($i >= 22952) {
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
				}

				$i++;

			}
			rename($this->pending_folder.'/'.$this->file.'.'.$this->ext, $this->processed_folder.'/'.$this->file.'.'.$this->ext);
		}
		die('FIN');

	}

	public function customers($days = 0, $hist = "")
	{
		ini_set('max_execution_time', 0);
		set_time_limit(0);

		$this->load->helper('directory');
		$this->load->helper('file');
		$this->load->helper('html');
		$this->load->helper('email');

		$this->file = $this->customers_file;

		if($hist === "hist"){
			$this->file = $this->file[0]."hist";
			$this->hist = true;
		} else {
			$this->file = $this->customers_file.date('Ymd', strtotime("-$days days"));
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
								 'document' => $file_row[0],
								];

						if($woowup->users->exist($file_row[0])){
							if (explode("@", $item['email'])[1] == "noemail.com") {
								unset($item['email']);
							}
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
		if (is_array($this->file)) {
			$items = [];
			foreach ($this->file as $file) {
				if (preg_match('/^'.$file.'([0-9]{6}).txt/i', $item_array[3])) {
					$items[] = $item;
				}
			}
			return $items;
		} elseif(preg_match('/^'.$this->file.'([0-9]{6}).txt/i', $item_array[3])){
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
		if ($this->hist === false) {
			$files = array_filter($this->ftp->list_files($this->remote_folder), array($this, '_findFile'));
		} else {
			$files[0] = $this->remote_folder . $this->file . '.' . $this->ext;
		}
		if(!empty($files)){
			$download = true;
			$counter = 0;
			foreach($files as $file){

				echo "Descargando el archivo $file \n";
				try {
					if (is_array($this->file)) {
						$download = $this->ftp->download($file, $this->pending_folder.'/'.$this->file[$counter].'.'.$this->ext, 'ascii');
					} else {
						$download = $this->ftp->download($file, $this->pending_folder.'/'.$this->file.'.'.$this->ext, 'ascii');
					}

					if($download !== true){
						throw new Exception("No se pudo descargar el archivo / Archivo no existe.",1);
					};
					//$delete = $this->ftp->delete_file($file);

				} catch(Exception $error) {
					echo $error->getMessage();
				}
				$counter ++;
			}
		} else {
			$download = false;
		}
		$this->ftp->close();

		return $download;

	}
}
