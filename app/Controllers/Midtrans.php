<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;


class Midtrans extends ResourceController
{
    protected $format    = 'json';
    protected $config;
    protected $uuid;
    protected $request;
    protected $validation;
    protected $user;
    protected $items, $shipping_address, $order, $callback1, $callback2, $callback3;
    public function __construct()
    {
        $this->request = \Config\Services::request();
        $this->callback1 = header('callback-1');
        $this->callback2 = header('callback-2');
        $this->callback3 = header('callback-3');
        $this->uuid = service('uuid');
        $this->config = array(
            'MD_MERCHANT_ID' => $_ENV["MIDTRANS_MERCHANT_ID"],
            'MD_SERVER_KEY' => $_ENV["MIDTRANS_SERVER_KEY"],
            'MD_CLIENT_KEY' => $_ENV["MIDTRANS_CLIENT_KEY"],
        );
        $this->validation =  \Config\Services::validation();
        // Set your Merchant Server Key
        \Midtrans\Config::$serverKey = $_ENV["MIDTRANS_SERVER_KEY"];
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = $_ENV["CI_ENVIRONMENT"] == 'production' ? true : false;
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = $_ENV["MIDTRANS_SANITIZED"];
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = $_ENV["MIDTRANS_3DS"];
    }

    public function getuser($token)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $_ENV['AUTH_API_URL'].'/api/profile?include=addresses,personal-information',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer '.$token
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        // echo $response;
        // print_r($response);exit;
        if($response === false){
            // print_r(curl_error($curl));exit;
            return false;
        }
        $response = json_decode($response);
        return $response->data;
        
    }

    public function validauth()
    {
        
        // $this->validation->reset();
        $post = $this->request->getPost();

        if (!isset($post['email']) && !isset($post['usertoken'])) {
            return $this->respond(array(
                'error' => 1,
                'message' => 'user tidak dapat ditemukan!'
            ));
            exit;
        }

        if (!isset($post['usertoken']) && isset($post['email'])) {
            return 'email';
        } else {
            $this->user = $this->getuser($post['usertoken']);
            return;
        }

        return;
    }

    public function validbasic()
    {
        
        $this->validation->reset();
        $this->validation->setRules([
            'module' => ['label' => 'Nama Modul', 'rules' => 'required'],
            'gross_amount' => ['label' => 'Total bayar', 'rules' => 'required|numeric'],
            'id_module' => ['label' => 'ID Modul', 'rules' => 'required|numeric']
        ]);
        $this->validation->withRequest($this->request)->run();
        $validation_error = $this->validation->getErrors();
        
        if (count($validation_error) > 0) {
            // print_r('validauth');exit;
            return $this->respond(array(
                'error' => 1,
                'message' => $validation_error
            ));
            exit;
        }

        return;
    }

    public function validbasic2()
    {
        $this->validation->reset();
        $this->validation->setRules([
            'email' => ['label' => 'Email', 'rules' => 'required', 'errors' => ['required' => 'Email dibutuhkan untuk mengirim pembayaran!']],
            'full_name' => ['label' => 'Nama Lengkap', 'rules' => 'required'],
            'phone' => ['label' => 'No HP', 'rules' => 'required']
        ]);
        $this->validation->withRequest($this->request)->run();
        $validation_error = $this->validation->getErrors();

        if (count($validation_error) > 0) {
            $this->respond(array(
                'error' => 1,
                'message' => $validation_error
            ));
            exit;
        }

        return;
    }

    public function validolshop()
    {
        $this->validation->reset();
        $this->validation->setRules([
            'shipping_address.*' => ['label' => 'Alamat Pengiriman', 'rules' => 'required'],
            'shipping_rate' => ['label' => 'Ongkir', 'rules' => 'required|numeric']
        ]);
        $this->validation->withRequest($this->request)->run();
        $validation_error = $this->validation->getErrors();

        if (count($validation_error) > 0) {
            return $this->respond(array(
                'error' => 1,
                'message' => $validation_error
            ));
            exit;
        }

        $this->items[] = array(
            'id' => 'ongkir',
            'price'    => $this->request->getPost('shipping_rate'),
            'quantity' => 1,
            'name'     => 'Ongkir'
        );

        return;
    }


    public function getsnap()
    {
        // print_r('getsnap');exit;
        $this->validbasic();
        $this->validauth();

        $post = $this->request->getPost();

        $this->order = array(
            'transaction_details' => array(
                'order_id' => $post['module'] . $this->uuid->uuid4()->toString(),
                'gross_amount' => $post['gross_amount'],
            ),
        );
        
        // Populate items
        if($post['module'] == 'olshop'){
            $this->validolshop();
            if(isset($post['items[]'])){
                foreach ($post['items[]'] as $item) {
                    $this->items[] = array(
                        'id' => $item->id,
                        'price'    => $item->price,
                        'quantity' => $item->qty,
                        'name'     => $item->name
                    );
                }
                $this->order['items'] = $this->items;
            }
            
    
            // Populate customer's shipping address
            if(isset($post['shipping_address'])){
            if($this->user != null && $this->user->id != null){
                $adr = "";
                foreach($this->user->address as $addr){
                    $adr = $addr->full_address.' '.$addr->street.' '.$addr->city.' '.$addr->state;
                }
                $this->shipping_address = array(
                    'first_name'   => $this->user->name,
                    'last_name'    => "-",
                    'address'      => $adr,
                    'city'         => $this->user->city,
                    'postal_code'  => $this->user->postal_code,
                    'phone'        => $this->user->name->personal_information->phone_number,
                    'country_code' => 'ID'
                );
                $this->order['shipping_address'] = $this->shipping_address;
            }}
            
        }else if($post['module'] == 'donation'){
            $this->items[] = array(
                'id' => $post['module'],
                'price'    => $post['gross_amount'],
                'quantity' => 1,
                'name'     => $post['title']
            );
            $this->order['items'][] = $this->items;
        }
    
        $snapToken = \Midtrans\Snap::getSnapToken($this->order);
        return $this->respond(['snaptoken' => $snapToken]);
      
    }
}
