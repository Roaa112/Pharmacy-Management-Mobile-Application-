<?php

namespace App\Modules\Cart\Repositories;

use App\Models\Cart;
use App\Modules\Shared\Repositories\BaseRepository;

class CartRepository extends BaseRepository
{
    public function __construct(private Cart $model)
    {
        parent::__construct($model);
    }

    public function getUserCart($userId)
    {
        return Cart::with('product')->where('user_id', $userId)->get();
    }

    public function addOrUpdate($data)
    {
        $cartItem = Cart::where('user_id', $data['user_id'])
                        ->where('product_id', $data['product_id'])
                        ->first();

        if ($cartItem) {
            $cartItem->product_quantity += $data['product_quantity'];
            $cartItem->total_price = $cartItem->product_quantity * $cartItem->price_at_time;
            $cartItem->save();
        } else {
            $data['total_price'] = $data['product_quantity'] * $data['price_at_time'];
            $cartItem = Cart::create($data);
        }

        return $cartItem;
    }

   

    public function delete($cartId)
    {
        $cartItem = Cart::find($cartId);
        if (!$cartItem) {
            return false;
        }
    
        return $cartItem->delete();
    }
    
    // public function updateQuantity($cartId, $quantity)
    //     {
    //         $cart = Cart::findOrFail($cartId);
    //         $cart->product_quantity = $quantity;
    //         $cart->total_price = $quantity * $cart->price_at_time;
    //         $cart->save();
    //         return $cart;
    //     }
    // public function clearCart($userId)
    // {
    //     return Cart::where('user_id', $userId)->delete();
    // }
}

  
