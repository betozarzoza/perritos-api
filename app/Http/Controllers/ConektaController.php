<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Conekta\Conekta;
use App\Models\User;
use App\Models\Purchases;
use App\Models\CoursePurchase;
use App\Models\Extra;

class ConektaController extends Controller
{
	public function __construct(){
    Conekta::setApiKey(env('CONEKTA_API_KEY'));
  }

  public function getConektaUser(Request $request){
  	$user = $request->user();
    if ($user->conekta_id) {
      $customer = \Conekta\Customer::find($user->conekta_id);
      return $customer;
    }
  }

  public function deleteCard (Request $request) {
    $user = $request->user();
    $customer = \Conekta\Customer::find($user->conekta_id);
    $source   = $customer->payment_sources[$request->id]->delete();
    return $source;
  }

  public function getcards (Request $request) {
      
  }

  public function addCard(Request $request)
  {
  	$user = $request->user();
    $customer = \Conekta\Customer::find($user->conekta_id);
		$source = $customer->createPaymentSource([
	  'token_id' => $request->id,
	  'type'     => 'card'
	]);
	return $source;
  }

  public function checkout(Request $request) {
      $user = $request->user();
      $total = 0;
      $count_course_purchases = 0;
      for ($i = 0; $i < count($request->courses); $i++) {
        $course_purchase = CoursePurchase::where('product_id', $request->courses[$i]['id'])->where('user_id', $user->id)->count();
        if ($course_purchase) {
          return response()->json([
            'message' => 'Ya tienes comprado el modulo ' . $request->courses[$i]['title']
          ], 404);
        }
      }
      for ($i = 0; $i < count($request->courses); $i++) {
          $total += $request->courses[$i]['price'];
      }
      $total = $total * 100;
      if ($total > 0) {
        $order = \Conekta\Order::create([
          'currency' => 'USD',
          'customer_info' => [
            'customer_id' => $user->conekta_id
          ],
          'line_items' => [
            [
              'name' => 'Cursos',
              'unit_price' => $total,
              'quantity' => 1
            ]
          ],
          'charges' => [
                [
                    'payment_method' => [
                        'payment_source_id' =>  $request->payment_id,
                        'type' => 'card'
                    ]
                ]
            ]
        ]);
      }
      if (isset($order) && $order->payment_status == 'paid') {
        for ($i = 0; $i < count($request->courses); $i++) {
          $purchase = new CoursePurchase;
          $purchase->price = $request->courses[$i]['price'];
          $purchase->product_id = $request->courses[$i]['id'];
          $purchase->user_id = $user->id;
          $purchase->save();
        }
        return $order;
      } else if ($total == 0) {
        for ($i = 0; $i < count($request->courses); $i++) {
          $purchase = new CoursePurchase;
          $purchase->price = $request->courses[$i]['price'];
          $purchase->product_id = $request->courses[$i]['id'];
          $purchase->user_id = $user->id;
          $purchase->save();
        }
      } else {
        return response()->json([
          'message' => 'Hubo un error en el pago'
        ], 500);
      }
  }
}
