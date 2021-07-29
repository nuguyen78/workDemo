<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

use App\Model\Products;

class Presenter extends BasePresenter {



    private $Products;

    public function __construct(Products $Products) {

        $this->Products = $Products;

    }

    protected function startup() {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
        $this->logManager->writeToLog('onpage');
        $this->template->quantityCart = $this->Products->quantityCart();

    }

    public function beforeRender() {
		parent::beforeRender();

		// vlastní filtry
		$this->template->addFilter( 'dateCZ', function( $date ) {
			return date('j. n. Y', strtotime($date));
		} );

	
	}

//přidá názvý k produktům
    public function actionCron(){
      $products = array();
      $products = $this->Products->SelectPLU();
        $url = 'url';
        $this->payload->test = __DIR__;
        $data = array(
            "api_key" => "key",
            "products" => $products
            );
          $data = json_encode($data);
        $ch = curl_init( $url );
        # Setup request to send json via POST.
        
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        # Return response instead of printing.
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        # Send request.
        $result = curl_exec($ch);
        //echo 'Curl error: ' . curl_error($ch);
        curl_close($ch);
        # Print response.
        $this->payload->stocks = $result;
         $stocks = array();
        $stocks = json_decode($result, true);
           foreach($products as $key){
            $plu = $key;
            $stock_eshop =  $stocks['products'][$key]['stock_eshop'];
            $stock_prodejna =  $stocks['products'][$key]['stock_prodejna'];
            $stock_mimitex =  $stocks['products'][$key]['stock_mimitex'];
            $stock_all =  $stocks['products'][$key]['stock_all'];
            $product_name =  $stocks['products'][$key]['product_name'];

            //array produktů
            $category = $this->Products->CategoryNameParse();
            $product_name = " ".$product_name." ";
            foreach ($category as $name){
              $product_name = str_ireplace(' '.$name.' ', ' ', $product_name);
              //$product_name = str_replace($name, '', $product_name);
              
            }

            $product_name = trim($product_name);
            //specialtky
            $product_name = str_replace('by MIMI', '', $product_name);
            $product_name = trim($product_name);

            //první písmeno velké
            //$product_name = mb_strtolower( $product_name, 'UTF-8' );
            $string_len = mb_strlen( $product_name, 'UTF-8' );
            $first_letter = mb_substr( $product_name, 0, 1, 'UTF-8' );
            $first_letter = mb_strtoupper( $first_letter, 'UTF-8' );
            $rest_of_string = mb_substr( $product_name, 1, $string_len, 'UTF-8' );
            $product_name = $first_letter . $rest_of_string;
            $this->Products->Updatestocks($key, $stock_eshop, $stock_prodejna, $stock_mimitex, $stock_all, $product_name); 
           }
 
           $this->sendPayload(); 
    }
    public function renderHistory(int $page = 1): void {
        //stránkování
        $count = $this->Products->ordersHistoryCount();
        $paginator = new Nette\Utils\Paginator;
        $paginator->setItemCount($count);
        $paginator->setItemsPerPage(30);
        $paginator->setPage($page);
        $offset = 8;
        $orders = $this->Products->ordersHistory($paginator->getLength() , $paginator->getOffset());
        if ($paginator->getPage() > 4 and $paginator->getPage() < $paginator->getLastPage() - 2) {
            $offset = $paginator->getPage() + 4;
        }
        elseif ($paginator->getPage() > 4) {
            $offset = $paginator->getLastPage() + 1;
        }

        /*         if ($paginator->getPage() > $paginator->getLastPage()-3) {
        $offset = $paginator->getLastPage();
        } */
        $this->template->offset = $offset;
        $this->template->paginator = $paginator;
        $this->template->orders = $orders;
    }
    public function handleChangePriceSend() {
        $httpRequest = $this->getHttpRequest();
        $all = $httpRequest->getPost();
        $this->Products->PriceSend($all);
        if (isset($all['order_id'])) {
            $this->template->calculations = $this->Products->calculations($all['order_id']);
        }
        $this->redrawControl('allsend');
    }

    public function handleUpload() {
        $i = 1;
        $img_path = $this->NewImages->getPath();
        $ds = DIRECTORY_SEPARATOR;
        $targetPath = $img_path . $ds;
        if (!empty($_FILES)) {

            $tempFile = $_FILES['file']['tmp_name'];
            $allowedTypes = array(
                IMAGETYPE_TIFF_II,
                IMAGETYPE_TIFF_MM
            );
            $detectedType = exif_imagetype($tempFile);
            if (in_array($detectedType, $allowedTypes)) {
                $image = new \Imagick($tempFile);
                $width = $image->getImageWidth();
                $height = $image->getImageHeight();
                $date = date("d.m.Y h:i");
                $img_hashTiff = substr(md5($tempFile . $date) , 0, 15) . '.tiff';
                $img_hashJpg = substr(md5($tempFile . $date) , 0, 15) . '.jpg';
                while (file_exists($targetPath . $img_hashTiff)) {
                    $img_hashTiff = substr(md5($tempFile . $date . $i) , 0, 15) . '.tiff';
                    $img_hashJpg = substr(md5($tempFile . $date . $i) , 0, 15) . '.jpg';
                    $i++;
                }
                $targetFile = $targetPath . $img_hashTiff;
                move_uploaded_file($tempFile, $targetFile);
                $img_size = filesize($targetFile);
                $image->setImageFormat('jpg');
                //$image->resizeImage(600, 400, \Imagick::FILTER_LANCZOS, 1, true);
                $max_width  = 600;
                $max_height = 400;
                $image->resizeImage(
                    min($image->getImageWidth(),  $max_width),
                    min($image->getImageHeight(), $max_height),
                    \Imagick::FILTER_CATROM,
                    1,
                    true
                );

                //Convert to centimeter
                $l = $width * 2.54 / 300;
                $h = $height * 2.54 / 300;

                //$image->writeImage('something.png');
                file_put_contents($targetPath . $img_hashJpg, $image);
                $this->NewImages->addImg($img_hashTiff, $img_size, $l, $h);
            }
        }
    }
}