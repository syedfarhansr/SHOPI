<?php

namespace App\Http\Livewire;

use App\Mail\OrderReceived;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InvoiceService;
use Livewire\Component;
use Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Checkout extends Component
{


    public function success(Request $request)
    {
        $lineItems = [];
        foreach (Cart::content() as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $item->model->name,
                    ],
                    'unit_amount' => $item->model->price * 100,
                ],
                'quantity' => $item->qty,
            ];
        }

        $total = str_replace(',', '', Cart::total());
        $order = new Order([
            'user_id' => Auth::user()->id,
            'status' => 'pending',
            'total' => $total,
            'session_id' => md5(Auth::user()->id),
        ]);
        $order->save();

        foreach (Cart::content() as $item) {
            $price = str_replace(',', '', $item->price);
            $orderItem = new OrderItem([
                'order_id' => $order->id,
                'product_id' => $item->model->id,
                'quantity' => $item->qty,
                'price' => $price
            ]);
            $orderItem->save();
        }

        $order = Order::where('user_id', auth()->id())->first();
        $order->status = 'processing';
        $order->save();
        Cart::destroy();
        return view('livewire.success');
    }

    public function cancel()
    {
        return redirect()->route('home')->with('success', 'Your order has been canceled.');
    }

    public function makeOrder(Request $request)
    {
        $validatedRequest = $request->validate([
            'country' => 'required',
            'billing_address' => 'required',
            'city' => 'required',
            'state' => 'required',
            'phone' => 'required',
            'zipcode' => 'required|numeric',
            'order_notes' => '',
        ]);

        $user = Auth::user();
        if ($user->billingDetails === null) {
            $user->billingDetails()->create($validatedRequest);
        } else {
            $user->billingDetails()->update($validatedRequest);
        }



        return redirect()->route('checkout.success');
    }

    public function render()
    {
        if (Cart::count() <= 0) {
            session()->flash('error', 'Your cart is empty.');
            return redirect()->route('home');
        }
        $user = Auth::user();
        $billingDetails = $user->billingDetails;
        return view('livewire.checkout', compact('billingDetails'));
    }
}
