<?php

namespace App\Http\Controllers\Api\Front;

use App\Exceptions\NotAuthorized;
use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserAddressResource;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ResponseJson;
use Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class OrderController extends Controller
{
    protected $name;
    protected $email;

    use ResponseJson;

    public function getUserAddress(Request $request){
        $authUserId = Auth::user()->id;
        $address= User::find($request->id);
        if(!$address){
            throw new NotFound;
        }
        if($authUserId!=$address->id){
            throw new NotAuthorized;
        }
        return $this->jsonResponseWithoutMessage(new UserAddressResource($address), 'data', 200);
    }

    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'quantity' => 'required|numeric',
            'price' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->jsonResponseWithoutMessage($validator->errors(), 'data', 500);
        }

        $product = Product::find($request->product_id);
        if (!$product){
            throw new NotFound;
        }
        if($request->quantity<=0){
            return $this->jsonResponseWithoutMessage("At least select one Item", 'data', 200);
        }

        if ($product->stock_quantity >= $request->quantity){
            $productId= session()->get('cart.id');
            $productQuantity= session()->get('cart.quantity');

            $arrayIndex=false;
            if($productId && count($productId)>0){
                $arrayIndex= array_search($request->product_id,$productId,true);
            }
            if(strval($arrayIndex) != ""){
                $productQuantity[$arrayIndex]=$request->quantity;
                session()->put('cart.quantity',$productQuantity);
            }else{
                session()->push('cart.id',$request->product_id);
                session()->push('cart.quantity',$request->quantity);
                session()->push('cart.price',$request->price);
            }
        }else{
            return $this->jsonResponseWithoutMessage("Out Of Stock Or add less quantity", 'data', 200);
        }
        return $this->jsonResponseWithoutMessage('Your item Added Successfully', 'data', 200);
    }

    public function getCartData()
    {
        $carts= session()->get('cart');
        if(isset($carts) && count($carts)>0 && isset($carts['id']) && count($carts['id'])>0){
            $subTotal=0;
            for($i=0;$i<count($carts['id']);$i++){
                $product = Product::find($carts['id'][$i]);
                if($product->stock_quantity>=$carts['quantity'][$i]){
                    $subTotal+=$carts['quantity'][$i] * $carts['price'][$i];
                }else{
                    return $this->jsonResponseWithoutMessage("Out Of Stock", 'data', 200);
                }
            }
            return $this->jsonResponseCartWithSubTotal($carts, 'data', 200,$subTotal);
        }else{
            return $this->jsonResponseWithoutMessage("No Items In Cart", 'data', 200);
        }
    }

    public function create(Request $request)
    {
        $carts = session()->get('cart');
        $validator = Validator::make($request->all(), [
            'shipping_address_ar' => 'required',
            'shipping_address_en' => 'required',
            'shipping_google_address' => 'required',
            'payment_method' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->jsonResponseWithoutMessage($validator->errors(), 'data', 500);
        }

        $subTotal=0;
        if(isset($carts) && count($carts)>0){
            for($i=0;$i<count($carts['id']);$i++){
                $product = Product::find($carts['id'][$i]);
                if($product->stock_quantity>=$carts['quantity'][$i]){
                    $subTotal+=$carts['quantity'][$i] * $carts['price'][$i];
                }else{
                    return $this->jsonResponseWithoutMessage("Out Of Stock", 'data', 200);
                }
            }
            $taxValue=$subTotal*(15/100);
            $shippingValue=25;
            $finalTotal=$subTotal+$taxValue+$shippingValue;

            $order=   Order::create([
                'product_attributes' => serialize($carts), //products
                'user_id' => Auth::user()->id,
                'shipping_address_ar' => $request->shipping_address_ar,
                'shipping_address_en' => $request->shipping_address_en,
                'shipping_google_address' => $request->shipping_google_address,
                'shipping_date' => now(),
                'payment_method' => $request->payment_method,
                'subtotal' => $subTotal,
                'shipping_cost' =>$shippingValue,
                'taxes' => $taxValue,
                'final_total' => $finalTotal,
                'is_notified' => 0,
            ]);

            for($i=0;$i<count($carts['id']);$i++){
                $product = Product::find($carts['id'][$i]);
                $product->stock_quantity-=$carts['quantity'][$i];
                $product->save();
            }
        }else{
            return $this->jsonResponseWithoutMessage("There is no items in the cart", 'data', 200);
        }

        $user=Auth::user();
        $this->email=$user->email;
        $this->name=$user->name;
        $data = array('user'=>$user);
        Mail::send('mail', $data, function($message) {
            $message->to($this->email, $this->name)->subject
            ('Laravel HTML Testing Mail');
            $message->from('xyz@gmail.com','Virat Gandhi');
        });

        if($request->payment_method!= 2){
            $payment_method = PaymentMethod::find($request->payment_method);
            if($payment_method)
            {
                $apiURL = 'https://apitest.myfatoorah.com';
                $apiKey ='rLtt6JWvbUHDDhsZnfpAhpYk4dxYDQkbcPTyGaKp2TYqQgG7FGZ5Th_WD53Oq8Ebz6A53njUoo1w3pjU1D4vs_ZMqFiz_j0urb_BH9Oq9VZoKFoJEDAbRZepGcQanImyYrry7Kt6MnMdgfG5jn4HngWoRdKduNNyP4kzcp3mRv7x00ahkm9LAK7ZRieg7k1PDAnBIOG3EyVSJ5kK4WLMvYr7sCwHbHcu4A5WwelxYK0GMJy37bNAarSJDFQsJ2ZvJjvMDmfWwDVFEVe_5tOomfVNt6bOg9mexbGjMrnHBnKnZR1vQbBtQieDlQepzTZMuQrSuKn-t5XZM7V6fCW7oP-uXGX-sMOajeX65JOf6XVpk29DP6ro8WTAflCDANC193yof8-f5_EYY-3hXhJj7RBXmizDpneEQDSaSz5sFk0sV5qPcARJ9zGG73vuGFyenjPPmtDtXtpx35A-BVcOSBYVIWe9kndG3nclfefjKEuZ3m4jL9Gg1h2JBvmXSMYiZtp9MR5I6pvbvylU_PP5xJFSjVTIz7IQSjcVGO41npnwIxRXNRxFOdIUHn0tjQ-7LwvEcTXyPsHXcMD8WtgBh-wxR8aKX7WPSsT1O8d8reb2aR7K3rkV3K82K_0OgawImEpwSvp9MNKynEAJQS6ZHe_J_l77652xwPNxMRTMASk1ZsJL';
                $postFields = [
                    //Fill required data
                    'NotificationOption' => 'Lnk', //'SMS', 'EML', or 'ALL'
                    'InvoiceValue'       => $finalTotal,
                    'CustomerName'       => $user->name_en,
                    //Fill optional data
                    'DisplayCurrencyIso' => 'EGP',
                    //'MobileCountryCode'  => '+965',
                    //'CustomerMobile'     => '1234567890',
                    'CustomerEmail'      => $user->email,
                    //'CallBackUrl'        => 'https://example.com/callback.php',
                    //'ErrorUrl'           => 'https://example.com/callback.php', //or 'https://example.com/error.php'
                    //'Language'           => 'en', //or 'ar'
                    'CustomerReference'  => $order->id,
                    //'CustomerCivilId'    => 'CivilId',
                    //'UserDefinedField'   => 'This could be string, number, or array',
                    //'ExpiryDate'         => '', //The Invoice expires after 3 days by default. Use 'Y-m-d\TH:i:s' format in the 'Asia/Kuwait' time zone.
                    //'SourceInfo'         => 'Pure PHP', //For example: (Laravel/Yii API Ver2.0 integration)
                    //'CustomerAddress'    => $customerAddress,
                    //'InvoiceItems'       => $invoiceItems,
                ];

                $curl = curl_init("$apiURL/v2/SendPayment");
                curl_setopt_array($curl, array(
                    CURLOPT_CUSTOMREQUEST  => 'POST',
                    CURLOPT_POSTFIELDS     => json_encode($postFields),
                    CURLOPT_HTTPHEADER     => array("Authorization: Bearer $apiKey", 'Content-Type: application/json'),
                    CURLOPT_RETURNTRANSFER => true,
                ));
                curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
                $response = curl_exec($curl);
                // dd($response);
                $curlErr  = curl_error($curl);
                curl_close($curl);
                $json = json_decode($response);
                if($json->IsSuccess){
                    $invoice_id =  $json->Data->InvoiceId;
                    return $this->jsonResponseWithoutMessage("Created Successfully and your invoice is ". $invoice_id." and your Invoice URL Is ".$json->Data->InvoiceURL, 'data', 200);
                }else{
                    return $json->ValidationErrors[0]->Error;
                }

            }else{
                return 'exception';
            }

        }

        return $this->jsonResponseWithoutMessage("Created Successfully", 'data', 200);

    }
    public function removeFromCart(Request $request)
    {
        $product= Product::find($request->product_id);
        if(!$product){
            throw new NotFound;
        }
        $carts = session()->get('cart');
        $productCartId= session()->get('cart.id');
        $productCartQuantity= session()->get('cart.quantity');
        $productCartPrice= session()->get('cart.price');

        if($carts && count($carts)>0){
            if($productCartId && count($productCartId)>0){
                $arrayIndex= array_search($request->product_id,$productCartId,true);
                if(strval($arrayIndex)!= ""){
                    array_splice($productCartId, $arrayIndex,1);
                    array_splice($productCartQuantity, $arrayIndex,1);
                    array_splice($productCartPrice, $arrayIndex,1);
                    session()->put('cart.id', $productCartId);
                    session()->put('cart.quantity', $productCartQuantity);
                    session()->put('cart.price', $productCartPrice);
                }else{
                    return $this->jsonResponseWithoutMessage("This product does not exist in cart", 'data', 200);
                }
            }
            return $this->jsonResponseWithoutMessage("item removed successfully", 'data', 200);

        }else{
            return $this->jsonResponseWithoutMessage("There is no item to remove", 'data', 200);
        }
    }

    public function show(Request $request){
        $order = Order::find($request->id);
        if(!$order){
            throw new NotFound;
        }
        if(Auth::user()->hasRole('user')){
            if( Auth::user()->id!=$order->user_id){
                throw new NotAuthorized;
            }
        }
        return $this->jsonResponseWithoutMessage(new OrderResource($order), 'data', 200);
    }

    public function updateStatus(Request $request)
    {
        if (Auth::user()->hasRole('vendor') || Auth::user()->hasRole('admin')) {
            $orderStatus = Order::find($request->id);
            if (!$orderStatus) {
                throw new NotFound;
            }
            $orderStatus->update([
                'order_status' => $request->order_status
            ]);
            $user = User::find($orderStatus->user_id);
            $this->email = $user->email;
            $this->name = $user->name;
            $data = array('user' => $user);
            Mail::send('mail', $data, function ($message) {
                $message->to($this->email, $this->name)->subject
                ('Your Order Is Completed');
                $message->from('xyz@gmail.com', 'Virat Gandhi');
            });
            return $this->jsonResponseWithoutMessage('Order Status Updated Successfully', 'data', 200);
        }else{
            throw new NotAuthorized;
        }
    }
}
